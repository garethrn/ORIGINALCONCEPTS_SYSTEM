<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();

$pageTitle  = 'Create Ticket';
$errors     = [];
$formData   = [];

$clients     = dbRows("SELECT id, name, company, email FROM clients WHERE is_active = 1 ORDER BY name");
$technicians = dbRows("SELECT id, name FROM users WHERE role IN ('admin','manager','technician') AND is_active = 1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $formData = [
            'client_id'   => (int)($_POST['client_id']   ?? 0),
            'title'       => sanitize($_POST['title']      ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'category'    => sanitize($_POST['category']   ?? ''),
            'priority'    => sanitize($_POST['priority']   ?? 'medium'),
            'assigned_to' => (int)($_POST['assigned_to']   ?? 0) ?: null,
            'location'    => sanitize($_POST['location']   ?? ''),
        ];

        // Validation
        if (empty($formData['title']))       $errors[] = 'Subject is required.';
        if (empty($formData['description'])) $errors[] = 'Description is required.';
        if (!array_key_exists($formData['category'], SERVICE_CATEGORIES)) $errors[] = 'Invalid service category.';
        if (!array_key_exists($formData['priority'], TICKET_PRIORITIES))  $errors[] = 'Invalid priority.';
        if ($formData['client_id'] && !dbRow('SELECT id FROM clients WHERE id = ?', [$formData['client_id']])) {
            $errors[] = 'Invalid client selected.';
        }

        if (empty($errors)) {
            // Handle attachment
            $attachmentPath = null;
            if (!empty($_FILES['attachment']['name'])) {
                $attachmentPath = handleUpload($_FILES['attachment'], 'tickets');
                if (!$attachmentPath) $errors[] = 'Invalid attachment. Allowed types: ' . implode(', ', UPLOAD_ALLOWED);
            }

            if (empty($errors)) {
                $ticketId = dbInsert('tickets', [
                    'client_id'      => $formData['client_id'] ?: null,
                    'title'          => $formData['title'],
                    'description'    => $formData['description'],
                    'category'       => $formData['category'],
                    'priority'       => $formData['priority'],
                    'status'         => 'open',
                    'assigned_to'    => $formData['assigned_to'],
                    'location'       => $formData['location'],
                    'attachment_path'=> $attachmentPath,
                    'created_by'     => currentUserId(),
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

                // Log history
                dbInsert('ticket_history', [
                    'ticket_id'  => $ticketId,
                    'user_id'    => currentUserId(),
                    'action'     => 'created',
                    'note'       => 'Ticket created.',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Email notification
                if ($formData['client_id']) {
                    $client = dbRow('SELECT name, email FROM clients WHERE id = ?', [$formData['client_id']]);
                    if ($client) {
                        $ticketData = array_merge($formData, ['id' => $ticketId, 'client_name' => $client['name'], 'client_email' => $client['email']]);
                        sendTicketNotification($ticketData, 'created');
                    }
                }

                redirectWithMessage(BASE_URL . '/tickets/view.php?id=' . $ticketId, 'Ticket ' . ticketRef($ticketId) . ' created successfully.');
            }
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/tickets/index.php" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Ticket</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">— Select Client (optional) —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($formData['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?><?= $c['company'] ? ' – ' . e($c['company']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Client not listed? <a href="<?= BASE_URL ?>/clients/create.php" target="_blank">Add new client</a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject / Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($formData['title'] ?? '') ?>"
                               placeholder="Brief description of the issue" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="6"
                                  placeholder="Provide as much detail as possible about the issue…" required><?= e($formData['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Service Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">— Select Category —</option>
                                <?php foreach (SERVICE_CATEGORIES as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($formData['category'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select" required>
                                <?php foreach (TICKET_PRIORITIES as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($formData['priority'] ?? 'medium') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <?php if (hasRole('admin', 'manager', 'technician')): ?>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Assign To</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($technicians as $tech): ?>
                                <option value="<?= $tech['id'] ?>" <?= ($formData['assigned_to'] ?? 0) == $tech['id'] ? 'selected' : '' ?>>
                                    <?= e($tech['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold"><i class="bi bi-geo-alt me-1"></i>Service Location</label>
                            <input type="text" name="location" class="form-control"
                                   value="<?= e($formData['location'] ?? '') ?>"
                                   placeholder="e.g. Client's office, Remote, …">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Attachment <small class="text-muted">(optional, max 10 MB)</small></label>
                        <input type="file" id="attachments" name="attachment" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.zip,.txt">
                        <ul id="filePreview" class="list-group mt-1"></ul>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Create Ticket
                        </button>
                        <a href="<?= BASE_URL ?>/tickets/index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Tips</div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Be as specific as possible in the description.</li>
                    <li class="mb-1">Include error messages, screenshots, or log files.</li>
                    <li class="mb-1">Set priority to <strong>Critical</strong> only for system-down situations.</li>
                    <li class="mb-1">The client will receive an email confirmation once the ticket is created.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
