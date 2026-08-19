<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'session') {
    $error = 'Your session could not be loaded. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($email, $password)) {
            $intended = $_SESSION['intended_url'] ?? '';
            unset($_SESSION['intended_url']);
            redirect($intended ?: BASE_URL . '/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card card">
        <div class="login-logo">
            <i class="bi bi-headset fs-1"></i>
            <h4 class="mt-2 mb-0 fw-bold"><?= COMPANY_NAME ?></h4>
            <p class="mb-0 opacity-75 small">Support Portal</p>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="you@example.com" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>

            <hr class="my-3">
            <p class="text-center text-muted small mb-0">
                Need help? Contact <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
