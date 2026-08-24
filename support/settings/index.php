<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin');

$pageTitle = 'Admin Settings';
$errors = [];

dbQuery("CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$defaultSettings = [
    'invoice_default_due_days' => '30',
    'invoice_default_tax_rate' => '15',
    'allow_manager_billing' => '1',
    'allow_manager_clients' => '1',
    'allow_technician_clients' => '1',
];
foreach ($defaultSettings as $settingKey => $settingValue) {
    dbQuery(
        'INSERT INTO app_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE setting_value = setting_value',
        [$settingKey, $settingValue]
    );
}

$form = [
    'invoice_default_due_days' => (int)appSetting('invoice_default_due_days', '30'),
    'invoice_default_tax_rate' => (float)appSetting('invoice_default_tax_rate', '15'),
    'allow_manager_billing' => appSettingBool('allow_manager_billing', true) ? '1' : '0',
    'allow_manager_clients' => appSettingBool('allow_manager_clients', true) ? '1' : '0',
    'allow_technician_clients' => appSettingBool('allow_technician_clients', true) ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $dueDays = max(1, min(365, (int)($_POST['invoice_default_due_days'] ?? 30)));
        $taxRate = max(0, min(100, (float)($_POST['invoice_default_tax_rate'] ?? 15)));
        $allowManagerBilling = isset($_POST['allow_manager_billing']) ? '1' : '0';
        $allowManagerClients = isset($_POST['allow_manager_clients']) ? '1' : '0';
        $allowTechClients = isset($_POST['allow_technician_clients']) ? '1' : '0';

        $form = [
            'invoice_default_due_days' => $dueDays,
            'invoice_default_tax_rate' => $taxRate,
            'allow_manager_billing' => $allowManagerBilling,
            'allow_manager_clients' => $allowManagerClients,
            'allow_technician_clients' => $allowTechClients,
        ];

        $settingsToSave = [
            'invoice_default_due_days' => (string)$dueDays,
            'invoice_default_tax_rate' => (string)$taxRate,
            'allow_manager_billing' => $allowManagerBilling,
            'allow_manager_clients' => $allowManagerClients,
            'allow_technician_clients' => $allowTechClients,
        ];

        foreach ($settingsToSave as $key => $value) {
            dbQuery(
                'INSERT INTO app_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
                [$key, $value]
            );
        }

        redirectWithMessage(BASE_URL . '/settings/index.php', 'Settings saved successfully.');
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Admin Settings</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" class="card">
            <div class="card-header"><i class="bi bi-gear me-2"></i>Billing & Client Access</div>
            <div class="card-body">
                <?= csrfField() ?>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Default Invoice Due Days</label>
                        <input type="number" min="1" max="365" name="invoice_default_due_days" class="form-control"
                               value="<?= (int)$form['invoice_default_due_days'] ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">Default VAT Rate (%)</label>
                        <input type="number" min="0" max="100" step="0.01" name="invoice_default_tax_rate" class="form-control"
                               value="<?= e((string)$form['invoice_default_tax_rate']) ?>" required>
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="allow_manager_billing" name="allow_manager_billing"
                        <?= $form['allow_manager_billing'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_manager_billing">
                        Allow <strong>Managers</strong> to access Billing (Invoices, Quotes, Payments, QuickBooks)
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="allow_manager_clients" name="allow_manager_clients"
                        <?= $form['allow_manager_clients'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_manager_clients">
                        Allow <strong>Managers</strong> to add/edit clients
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="allow_technician_clients" name="allow_technician_clients"
                        <?= $form['allow_technician_clients'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="allow_technician_clients">
                        Allow <strong>Technicians</strong> to add/edit clients
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Save Settings
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Admin Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>/invoices/create.php" class="btn btn-outline-success text-start">
                    <i class="bi bi-file-earmark-plus me-2"></i>Create Invoice
                </a>
                <a href="<?= BASE_URL ?>/clients/create.php" class="btn btn-outline-secondary text-start">
                    <i class="bi bi-person-plus me-2"></i>Add Client
                </a>
                <a href="<?= BASE_URL ?>/users/index.php" class="btn btn-outline-primary text-start">
                    <i class="bi bi-people me-2"></i>Manage Users
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
