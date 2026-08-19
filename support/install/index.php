<?php
/**
 * Original Concepts Support System – Web Installer
 * Visit: https://www.originalconcepts.co.za/support/install/
 *
 * DELETE THIS FOLDER after installation is complete.
 */

$installedFlag = __DIR__ . '/../config/.installed';

// Allow resetting the installation lock via ?reset=confirm
if (isset($_GET['reset']) && $_GET['reset'] === 'confirm' && file_exists($installedFlag)) {
    unlink($installedFlag);
    header('Location: ?step=1');
    exit;
}

// Prevent running if already installed (show reset option instead of hard die)
if (file_exists($installedFlag)) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Already Installed – Original Concepts Support System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a3c5e, #2d6a9f); min-height: 100vh; display:flex; align-items:center; }
        .install-card { max-width: 560px; width: 100%; margin: auto; border-radius: 12px; overflow: hidden; }
        .install-logo { background: rgba(255,255,255,.1); padding: 1.5rem; text-align: center; color:#fff; }
    </style>
</head>
<body class="py-5">
<div class="container">
    <div class="install-card card shadow-lg mx-auto">
        <div class="install-logo">
            <h3 class="fw-bold mb-0">Original Concepts Support System</h3>
            <p class="mb-0 opacity-75 small">Web Installer</p>
        </div>
        <div class="card-body p-4">
            <div class="alert alert-warning">
                <h5 class="alert-heading"><i class="bi bi-shield-lock me-2"></i>Already Installed</h5>
                <p class="mb-2">The system is already installed. For security, please <strong>delete the <code>/install</code> folder</strong> from your server.</p>
                <hr>
                <p class="mb-0 small">If you need to re-run the installer (e.g. after a failed setup), you can reset the installation lock below.</p>
            </div>
            <div class="d-grid gap-2">
                <a href="../auth/login.php" class="btn btn-primary">Go to Login Page →</a>
                <a href="?reset=confirm"
                   class="btn btn-outline-danger btn-sm"
                   onclick="return confirm('This will delete the installation lock and allow re-running the installer. Are you sure?')">
                    Reset Installation Lock &amp; Re-run Wizard
                </a>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>
<?php
    exit;
}

$step   = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$errors = [];
$success = false;

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ─── Step 2: Test DB & Run SQL ────────────────────────────────────────────
    $dbHost    = trim($_POST['db_host']    ?? 'localhost');
    $dbName    = trim($_POST['db_name']    ?? '');
    $dbUser    = trim($_POST['db_user']    ?? '');
    $dbPass    = $_POST['db_pass']         ?? '';
    $dbCharset = 'utf8mb4';

    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass']       ?? '';
    $siteUrl    = rtrim(trim($_POST['site_url'] ?? ''), '/');

    // Validate
    if (!$dbName)    $errors[] = 'Database name is required.';
    if (!$dbUser)    $errors[] = 'Database username is required.';
    if (!$adminName) $errors[] = 'Admin name is required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email required.';
    if (strlen($adminPass) < 8) $errors[] = 'Admin password must be at least 8 characters.';

    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};charset={$dbCharset}",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            // Run SQL install script
            $sql = file_get_contents(__DIR__ . '/install.sql');

            // Remove the default admin INSERT (we'll add our own)
            $sql = preg_replace('/-- ─── Default Admin.*$/s', '', $sql);
            $sql = preg_replace('/-- ─── Sample Client.*$/s', '', $sql);

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }

            // Insert admin user
            $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, 'admin', 1, NOW(), NOW())");
            $stmt->execute([$adminName, strtolower($adminEmail), $hashedPass]);

            // Write config file
            $configContent = file_get_contents(__DIR__ . '/../config/database.php');
            $configContent = str_replace("'localhost'", "'{$dbHost}'", $configContent);
            $configContent = str_replace("'oc_support'", "'{$dbName}'", $configContent);
            $configContent = str_replace("'oc_user'",    "'{$dbUser}'", $configContent);
            $configContent = str_replace("'your_db_password'", "'{$dbPass}'", $configContent);
            file_put_contents(__DIR__ . '/../config/database.php', $configContent);

            // Update site URL in config
            $mainConfig = file_get_contents(__DIR__ . '/../config/config.php');
            $mainConfig = str_replace(
                "define('SITE_URL',     'https://www.originalconcepts.co.za/support');",
                "define('SITE_URL',     '{$siteUrl}');",
                $mainConfig
            );
            file_put_contents(__DIR__ . '/../config/config.php', $mainConfig);

            // Mark as installed
            file_put_contents($installedFlag, date('Y-m-d H:i:s'));

            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install – Original Concepts Support System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a3c5e, #2d6a9f); min-height: 100vh; display:flex; align-items:center; }
        .install-card { max-width: 620px; width: 100%; margin: auto; border-radius: 12px; overflow: hidden; }
        .install-logo { background: rgba(255,255,255,.1); padding: 1.5rem; text-align: center; color:#fff; }
    </style>
</head>
<body class="py-5">
<div class="container">
    <div class="install-card card shadow-lg mx-auto">
        <div class="install-logo">
            <h3 class="fw-bold mb-0">Original Concepts Support System</h3>
            <p class="mb-0 opacity-75 small">Web Installer</p>
        </div>
        <div class="card-body p-4">

        <?php if ($success): ?>
            <div class="alert alert-success">
                <h5><i class="bi bi-check-circle me-2"></i>Installation Complete!</h5>
                <p class="mb-2">The system has been installed successfully.</p>
                <p class="mb-0">
                    <strong>⚠️ IMPORTANT:</strong> Delete the <code>/install</code> folder immediately for security.
                </p>
            </div>
            <div class="d-grid gap-2">
                <a href="../auth/login.php" class="btn btn-primary">Go to Login Page →</a>
            </div>

        <?php elseif ($step === 2 || !empty($errors)): ?>
            <h5 class="fw-bold mb-3">Database & Admin Setup</h5>

            <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>'; ?></ul>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="2">
                <h6 class="text-muted text-uppercase small fw-semibold mb-2 mt-3">Database Connection</h6>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">DB Host</label>
                        <input type="text" name="db_host" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Database Name</label>
                        <input type="text" name="db_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['db_name'] ?? 'oc_support') ?>" required placeholder="oc_support">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">DB Username</label>
                        <input type="text" name="db_user" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">DB Password</label>
                        <input type="password" name="db_pass" class="form-control form-control-sm">
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small fw-semibold mb-2">Administrator Account</h6>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Admin Name</label>
                        <input type="text" name="admin_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'System Administrator') ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold">Admin Email</label>
                        <input type="email" name="admin_email" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@originalconcepts.co.za') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Admin Password (min 8 chars)</label>
                        <input type="password" name="admin_pass" class="form-control form-control-sm" minlength="8" required>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small fw-semibold mb-2">Site Settings</h6>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Site URL</label>
                    <input type="url" name="site_url" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($_POST['site_url'] ?? 'https://www.originalconcepts.co.za/support') ?>" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Install Now</button>
            </form>

        <?php else: ?>
            <h5 class="fw-bold mb-3">Welcome to the Installer</h5>
            <p>This wizard will set up the Original Concepts Support System database and create your first administrator account.</p>

            <h6 class="fw-semibold">Requirements Check</h6>
            <?php
            $checks = [
                'PHP 7.4+'        => version_compare(PHP_VERSION, '7.4.0', '>='),
                'PDO MySQL'       => extension_loaded('pdo_mysql'),
                'JSON extension'  => extension_loaded('json'),
                'cURL extension'  => extension_loaded('curl'),
                'config/ writable'=> is_writable(__DIR__ . '/../config/'),
            ];
            ?>
            <ul class="list-group list-group-flush mb-3">
            <?php foreach ($checks as $name => $ok): ?>
                <li class="list-group-item d-flex justify-content-between py-2">
                    <span><?= $name ?></span>
                    <?php if ($ok): ?>
                    <span class="badge bg-success">✓ OK</span>
                    <?php else: ?>
                    <span class="badge bg-danger">✗ Failed</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>

            <?php $canInstall = !in_array(false, $checks, true); ?>
            <?php if ($canInstall): ?>
            <a href="?step=2" class="btn btn-primary w-100">Continue to Configuration →</a>
            <?php else: ?>
            <div class="alert alert-danger">Please fix the requirements above before continuing.</div>
            <?php endif; ?>
        <?php endif; ?>

        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</body>
</html>
