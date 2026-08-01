<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$ticket = dbRow(
    'SELECT t.*, c.name AS client_name, c.email AS client_email,
            c.phone AS client_phone, c.company AS client_company, c.address AS client_address,
            u.name AS assignee_name, cu.name AS created_by_name
     FROM tickets t
     LEFT JOIN clients c ON t.client_id = c.id
     LEFT JOIN users u ON t.assigned_to = u.id
     LEFT JOIN users cu ON t.created_by = cu.id
     WHERE t.id = ?',
    [$id]
);

if (!$ticket) {
    redirectWithMessage(BASE_URL . '/tickets/index.php', 'Ticket not found.', 'danger');
}

// Clients can only view their own tickets
if (hasRole('client') && $ticket['client_id'] !== currentUserId()) {
    redirect(BASE_URL . '/tickets/index.php');
}

$pageTitle = ticketRef($id) . ' – ' . $ticket['title'];

// ─── Handle Comment / Status / Assign Submission ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'comment') {
        $comment  = sanitize($_POST['comment']  ?? '');
        $internal = (int)($_POST['is_internal'] ?? 0);

        if ($comment) {
            $attachPath = null;
            if (!empty($_FILES['comment_attachment']['name'])) {
                $attachPath = handleUpload($_FILES['comment_attachment'], 'tickets/' . $id);
            }

            dbInsert('ticket_comments', [
                'ticket_id'       => $id,
                'user_id'         => currentUserId(),
                'comment'         => $comment,
                'is_internal'     => $internal,
                'attachment_path' => $attachPath,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            dbUpdate('tickets', ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);

            if (!$internal && $ticket['client_email']) {
                sendTicketNotification($ticket, 'updated');
            }

            redirectWithMessage(BASE_URL . '/tickets/view.php?id=' . $id, 'Comment added.');
        }
    }

    if ($action === 'update' && hasRole('admin', 'manager', 'technician')) {
        $newStatus   = sanitize($_POST['status']      ?? $ticket['status']);
        $newPriority = sanitize($_POST['priority']    ?? $ticket['priority']);
        $newAssignee = (int)($_POST['assigned_to']    ?? 0) ?: null;
        $resolution  = sanitize($_POST['resolution']  ?? '');

        $updates = [
            'status'      => $newStatus,
            'priority'    => $newPriority,
            'assigned_to' => $newAssignee,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($resolution) $updates['resolution'] = $resolution;

        if ($newStatus === 'resolved' && empty($ticket['resolved_at'])) {
            $updates['resolved_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($newStatus, ['closed', 'cancelled']) && empty($ticket['closed_at'])) {
            $updates['closed_at'] = date('Y-m-d H:i:s');
        }

        dbUpdate('tickets', $updates, ['id' => $id]);

        // Log history
        $note = "Status changed to {$newStatus}";
        if ($newAssignee !== (int)$ticket['assigned_to']) {
            $assigneeName = dbRow('SELECT name FROM users WHERE id = ?', [$newAssignee])['name'] ?? 'None';
            $note .= ", assigned to {$assigneeName}";
        }
        dbInsert('ticket_history', [
            'ticket_id'  => $id,
            'user_id'    => currentUserId(),
            'action'     => 'updated',
            'note'       => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Notify client
        if ($newStatus === 'resolved') {
            sendTicketNotification(array_merge($ticket, ['status' => $newStatus]), 'resolved');
        } elseif ($newStatus !== $ticket['status']) {
            sendTicketNotification(array_merge($ticket, ['status' => $newStatus]), 'updated');
        }

        redirectWithMessage(BASE_URL . '/tickets/view.php?id=' . $id, 'Ticket updated successfully.');
    }
}

// Reload ticket after potential updates
$ticket = dbRow(
    'SELECT t.*, c.name AS client_name, c.email AS client_email,
            c.phone AS client_phone, c.company AS client_company, c.address AS client_address,
            u.name AS assignee_name, cu.name AS created_by_name
     FROM tickets t
     LEFT JOIN clients c ON t.client_id = c.id
     LEFT JOIN users u ON t.assigned_to = u.id
     LEFT JOIN users cu ON t.created_by = cu.id
     WHERE t.id = ?',
    [$id]
);

$comments    = dbRows('SELECT tc.*, u.name AS author_name, u.role AS author_role FROM ticket_comments tc LEFT JOIN users u ON tc.user_id = u.id WHERE tc.ticket_id = ? ORDER BY tc.created_at ASC', [$id]);
$history     = dbRows('SELECT th.*, u.name AS actor_name FROM ticket_history th LEFT JOIN users u ON th.user_id = u.id WHERE th.ticket_id = ? ORDER BY th.created_at DESC LIMIT 20', [$id]);
$technicians = dbRows("SELECT id, name FROM users WHERE role IN ('admin','manager','technician') AND is_active = 1 ORDER BY name");
$invoices    = dbRows('SELECT i.id, i.total_amount, i.status, i.created_at FROM invoices i WHERE i.ticket_id = ? ORDER BY i.created_at DESC', [$id]);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/tickets/index.php">Tickets</a></li>
        <li class="breadcrumb-item active"><?= ticketRef($id) ?></li>
    </ol>
</nav>

<div class="row">
    <!-- ─── Main Ticket Panel ──────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Ticket Header -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge bg-secondary me-1 copy-ref" data-ref="<?= ticketRef($id) ?>" style="cursor:pointer" title="Click to copy"><?= ticketRef($id) ?></span>
                    <?= ticketStatusBadge($ticket['status']) ?>
                    <?= ticketPriorityBadge($ticket['priority']) ?>
                    <span class="badge bg-light text-dark border ms-1"><?= e(serviceCategoryLabel($ticket['category'])) ?></span>
                </div>
                <span class="text-muted small"><?= formatDateTime($ticket['created_at']) ?></span>
            </div>
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= e($ticket['title']) ?></h5>
                <div class="ticket-desc"><?= nl2br(e($ticket['description'])) ?></div>

                <?php if ($ticket['location']): ?>
                <p class="mt-3 mb-0 small">
                    <i class="bi bi-geo-alt text-secondary me-1"></i>
                    <strong>Location:</strong> <?= e($ticket['location']) ?>
                </p>
                <?php endif; ?>

                <?php if ($ticket['attachment_path']): ?>
                <p class="mt-2 mb-0 small">
                    <i class="bi bi-paperclip me-1"></i>
                    <a href="<?= BASE_URL ?>/uploads/<?= e($ticket['attachment_path']) ?>" target="_blank">Download Attachment</a>
                </p>
                <?php endif; ?>

                <?php if ($ticket['resolution'] && in_array($ticket['status'], ['resolved','closed'])): ?>
                <div class="alert alert-success mt-3 mb-0">
                    <strong><i class="bi bi-check-circle me-1"></i>Resolution:</strong><br>
                    <?= nl2br(e($ticket['resolution'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-chat-left-text me-2"></i>Comments & Updates</div>
            <div class="card-body">
                <?php if (empty($comments)): ?>
                <p class="text-muted text-center py-2">No comments yet.</p>
                <?php endif; ?>

                <?php foreach ($comments as $c): ?>
                <?php if ($c['is_internal'] && hasRole('client')) continue; ?>
                <div class="comment-item <?= $c['is_internal'] ? 'internal' : '' ?> mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="fw-semibold small">
                            <?= e($c['author_name'] ?? 'System') ?>
                            <?php if ($c['is_internal']): ?>
                            <span class="badge bg-warning text-dark ms-1 small">Internal Note</span>
                            <?php endif; ?>
                        </span>
                        <span class="text-muted small"><?= formatDateTime($c['created_at']) ?></span>
                    </div>
                    <div class="small"><?= nl2br(e($c['comment'])) ?></div>
                    <?php if ($c['attachment_path']): ?>
                    <div class="mt-1 small"><i class="bi bi-paperclip me-1"></i><a href="<?= BASE_URL ?>/uploads/<?= e($c['attachment_path']) ?>" target="_blank">Attachment</a></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Add Comment Form -->
                <?php if (!in_array($ticket['status'], ['closed', 'cancelled'])): ?>
                <hr>
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="comment">
                    <div class="mb-2">
                        <textarea name="comment" class="form-control form-control-sm" rows="4"
                                  placeholder="Add a comment or reply…" required></textarea>
                    </div>
                    <div class="mb-2">
                        <input type="file" name="comment_attachment" class="form-control form-control-sm"
                               accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif,.zip,.txt">
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <?php if (hasRole('admin','manager','technician')): ?>
                        <div class="form-check form-check-sm">
                            <input class="form-check-input" type="checkbox" name="is_internal" value="1" id="internalNote">
                            <label class="form-check-label small" for="internalNote">Internal note (not visible to client)</label>
                        </div>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-send me-1"></i> Post Comment
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Timeline -->
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Activity History</div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                <p class="text-muted small">No history yet.</p>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="d-flex justify-content-between">
                            <span class="small fw-semibold"><?= e($h['actor_name'] ?? 'System') ?></span>
                            <span class="text-muted small"><?= formatDateTime($h['created_at']) ?></span>
                        </div>
                        <div class="small text-muted"><?= e($h['note']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ─── Sidebar ────────────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Ticket Details / Edit -->
        <?php if (hasRole('admin','manager','technician')): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sliders me-2"></i>Ticket Management</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach (TICKET_STATUSES as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $ticket['status'] === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select name="priority" class="form-select form-select-sm">
                            <?php foreach (TICKET_PRIORITIES as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $ticket['priority'] === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Assigned To</label>
                        <select name="assigned_to" class="form-select form-select-sm">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($technicians as $tech): ?>
                            <option value="<?= $tech['id'] ?>" <?= $ticket['assigned_to'] == $tech['id'] ? 'selected' : '' ?>>
                                <?= e($tech['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Resolution Notes</label>
                        <textarea name="resolution" class="form-control form-control-sm" rows="3"
                                  placeholder="Describe how the issue was resolved…"><?= e($ticket['resolution'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Client Info -->
        <?php if ($ticket['client_id']): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person me-2"></i>Client Details</div>
            <div class="card-body small">
                <p class="mb-1"><strong><?= e($ticket['client_name']) ?></strong></p>
                <?php if ($ticket['client_company']): ?><p class="mb-1 text-muted"><i class="bi bi-building me-1"></i><?= e($ticket['client_company']) ?></p><?php endif; ?>
                <?php if ($ticket['client_email']): ?><p class="mb-1"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= e($ticket['client_email']) ?>"><?= e($ticket['client_email']) ?></a></p><?php endif; ?>
                <?php if ($ticket['client_phone']): ?><p class="mb-1"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($ticket['client_phone']) ?>"><?= e($ticket['client_phone']) ?></a></p><?php endif; ?>
                <?php if ($ticket['client_address']): ?><p class="mb-0"><i class="bi bi-geo-alt me-1"></i><?= e($ticket['client_address']) ?></p><?php endif; ?>
                <hr class="my-2">
                <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $ticket['client_id'] ?>" class="btn btn-sm btn-outline-primary w-100">View Client Profile</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Invoices for this ticket -->
        <?php if (hasRole('admin','manager') && !empty($invoices)): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt me-2"></i>Related Invoices</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($invoices as $inv): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="small fw-semibold text-decoration-none"><?= invoiceRef($inv['id']) ?></a>
                    <div class="text-end">
                        <div class="small"><?= money($inv['total_amount']) ?></div>
                        <?= invoiceStatusBadge($inv['status']) ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Create Invoice Button -->
        <?php if (hasRole('admin','manager') && !in_array($ticket['status'], ['cancelled'])): ?>
        <div class="d-grid mb-3">
            <a href="<?= BASE_URL ?>/invoices/create.php?ticket_id=<?= $id ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-plus me-1"></i> Create Invoice from Ticket
            </a>
            <a href="<?= BASE_URL ?>/quotes/create.php?ticket_id=<?= $id ?>" class="btn btn-outline-info btn-sm mt-2">
                <i class="bi bi-file-earmark-ruled me-1"></i> Create Quote from Ticket
            </a>
        </div>
        <?php endif; ?>

        <!-- Ticket Meta -->
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Ticket Info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created By</span><span><?= e($ticket['created_by_name'] ?? '—') ?></span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created At</span><span><?= formatDateTime($ticket['created_at']) ?></span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Last Updated</span><span><?= formatDateTime($ticket['updated_at']) ?></span></div>
                <?php if ($ticket['resolved_at']): ?>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Resolved At</span><span><?= formatDateTime($ticket['resolved_at']) ?></span></div>
                <?php endif; ?>
                <?php if ($ticket['closed_at']): ?>
                <div class="d-flex justify-content-between mb-0"><span class="text-muted">Closed At</span><span><?= formatDateTime($ticket['closed_at']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
