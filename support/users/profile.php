<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();

$pageTitle = 'My Profile';
$user      = currentUser();
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name  = sanitize($_POST['name']  ?? '');
            $email = strtolower(sanitize($_POST['email'] ?? ''));

            if (empty($name))            $errors[] = 'Name is required.';
            if (!validateEmail($email))  $errors[] = 'Valid email required.';

            $exists = dbRow('SELECT id FROM users WHERE email = ? AND id != ?', [$email, currentUserId()]);
            if ($exists) $errors[] = 'Email already in use.';

            if (empty($errors)) {
                dbUpdate('users', ['name' => $name, 'email' => $email, 'updated_at' => date('Y-m-d H:i:s')], ['id' => currentUserId()]);
                $_SESSION['user_name'] = $name;
                redirectWithMessage(BASE_URL . '/users/profile.php', 'Profile updated successfully.');
            }
        }

        if ($action === 'change_password') {
            $current  = $_POST['current_password']  ?? '';
            $new      = $_POST['new_password']       ?? '';
            $confirm  = $_POST['confirm_password']   ?? '';

            if (!password_verify($current, $user['password'])) $errors[] = 'Current password is incorrect.';
            if (strlen($new) < 8)                               $errors[] = 'New password must be at least 8 characters.';
            if ($new !== $confirm)                              $errors[] = 'Passwords do not match.';

            if (empty($errors)) {
                dbUpdate('users', ['password' => hashPassword($new), 'updated_at' => date('Y-m-d H:i:s')], ['id' => currentUserId()]);
                redirectWithMessage(BASE_URL . '/users/profile.php', 'Password changed successfully.');
            }
        }
    }
}

// Reload
$user = currentUser();
require_once dirname(__DIR__) . '/includes/header.php';
?>

<h4 class="mb-4 fw-bold"><i class="bi bi-person-circle me-2"></i>My Profile</h4>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <!-- Profile Info -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person me-2"></i>Personal Information</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <input type="text" class="form-control" value="<?= e(USER_ROLES[$user['role']] ?? ucfirst($user['role'])) ?>" readonly>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <!-- Change Password -->
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-warning btn-sm">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
