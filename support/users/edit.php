<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin');

$id   = (int)($_GET['id'] ?? 0);
$user = dbRow('SELECT * FROM users WHERE id = ?', [$id]);

if (!$user) {
    redirectWithMessage(BASE_URL . '/users/index.php', 'User not found.', 'danger');
}

// Prevent editing self through this page (use profile.php)
if ($id === currentUserId()) {
    redirect(BASE_URL . '/users/profile.php');
}

$pageTitle = 'Edit User: ' . $user['name'];
$errors    = [];
$formData  = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $formData = [
            'name'      => sanitize($_POST['name']  ?? ''),
            'email'     => strtolower(sanitize($_POST['email'] ?? '')),
            'role'      => sanitize($_POST['role']  ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (empty($formData['name']))  $errors[] = 'Name is required.';
        if (!validateEmail($formData['email'])) $errors[] = 'Valid email required.';
        if (!array_key_exists($formData['role'], USER_ROLES)) $errors[] = 'Invalid role.';

        $newPassword = $_POST['new_password'] ?? '';
        if ($newPassword && strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters.';

        $exists = dbRow('SELECT id FROM users WHERE email = ? AND id != ?', [$formData['email'], $id]);
        if ($exists) $errors[] = 'Email already in use.';

        if (empty($errors)) {
            $upd = array_merge($formData, ['updated_at' => date('Y-m-d H:i:s')]);
            if ($newPassword) $upd['password'] = hashPassword($newPassword);
            dbUpdate('users', $upd, ['id' => $id]);
            redirectWithMessage(BASE_URL . '/users/index.php', 'User updated successfully.');
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil me-2"></i>Edit User</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="row"><div class="col-lg-6">
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($formData['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e($formData['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <?php foreach (USER_ROLES as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $formData['role'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                    <input type="password" name="new_password" class="form-control" minlength="8">
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $formData['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button>
            </form>
        </div>
    </div>
</div></div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
