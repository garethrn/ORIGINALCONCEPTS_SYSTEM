<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager', 'technician');

$id     = (int)($_GET['id'] ?? 0);
$client = dbRow('SELECT * FROM clients WHERE id = ?', [$id]);

if (!$client) {
    redirectWithMessage(BASE_URL . '/clients/index.php', 'Client not found.', 'danger');
}

$pageTitle = 'Edit Client: ' . $client['name'];
$errors    = [];
$formData  = $client;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $formData = [
            'name'        => sanitize($_POST['name']        ?? ''),
            'company'     => sanitize($_POST['company']     ?? ''),
            'email'       => strtolower(sanitize($_POST['email']       ?? '')),
            'phone'       => sanitize($_POST['phone']       ?? ''),
            'address'     => sanitize($_POST['address']     ?? ''),
            'city'        => sanitize($_POST['city']        ?? ''),
            'province'    => sanitize($_POST['province']    ?? ''),
            'postal_code' => sanitize($_POST['postal_code'] ?? ''),
            'country'     => sanitize($_POST['country']     ?? 'South Africa'),
            'latitude'    => sanitize($_POST['latitude']    ?? ''),
            'longitude'   => sanitize($_POST['longitude']   ?? ''),
            'notes'       => sanitize($_POST['notes']       ?? ''),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (empty($formData['name'])) $errors[] = 'Name is required.';
        if ($formData['email'] && !validateEmail($formData['email'])) $errors[] = 'Invalid email address.';
        if ($formData['phone'] && !validatePhone($formData['phone'])) $errors[] = 'Invalid phone number.';
        if ($formData['email']) {
            $exists = dbRow('SELECT id FROM clients WHERE email = ? AND id != ?', [$formData['email'], $id]);
            if ($exists) $errors[] = 'Another client with this email already exists.';
        }

        if (empty($errors)) {
            dbUpdate('clients', array_merge($formData, [
                'latitude'   => $formData['latitude']  ? (float)$formData['latitude']  : null,
                'longitude'  => $formData['longitude'] ? (float)$formData['longitude'] : null,
                'email'      => $formData['email']      ?: null,
                'company'    => $formData['company']    ?: null,
                'phone'      => $formData['phone']      ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]), ['id' => $id]);

            redirectWithMessage(BASE_URL . '/clients/view.php?id=' . $id, 'Client updated successfully.');
        }
    }
}

$provinces = ['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Limpopo',
              'Mpumalanga','North West','Free State','Northern Cape'];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil me-2"></i>Edit Client</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-2"></i>Basic Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= e($formData['name']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Company</label>
                            <input type="text" name="company" class="form-control" value="<?= e($formData['company'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($formData['email'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($formData['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= e($formData['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $formData['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Active Client</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Location</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Street Address</label>
                            <input type="text" name="address" class="form-control" value="<?= e($formData['address'] ?? '') ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control" value="<?= e($formData['city'] ?? '') ?>">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Province</label>
                            <select name="province" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($provinces as $p): ?>
                                <option value="<?= $p ?>" <?= ($formData['province'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control" value="<?= e($formData['postal_code'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" name="country" class="form-control" value="<?= e($formData['country'] ?? 'South Africa') ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold small">Latitude</label>
                            <input type="text" name="latitude" id="latitude" class="form-control form-control-sm" value="<?= e($formData['latitude'] ?? '') ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold small">Longitude</label>
                            <input type="text" name="longitude" id="longitude" class="form-control form-control-sm" value="<?= e($formData['longitude'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i> Save Changes</button>
                <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
