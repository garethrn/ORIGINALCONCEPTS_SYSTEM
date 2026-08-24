<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager');

$pageTitle = 'QuickBooks Integration';

$qbToken = dbRow('SELECT * FROM qb_tokens ORDER BY created_at DESC LIMIT 1');
$isConnected = $qbToken && !empty($qbToken['access_token']);

// Build OAuth2 authorization URL
function buildQbAuthUrl(): string
{
    $params = [
        'client_id'     => QB_CLIENT_ID,
        'scope'         => QB_SCOPE,
        'redirect_uri'  => QB_REDIRECT_URI,
        'response_type' => 'code',
        'state'         => csrfToken(),
    ];
    return QB_AUTH_URL . '?' . http_build_query($params);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-link-45deg me-2"></i>QuickBooks Integration</h4>
</div>

<div class="row">
    <div class="col-lg-7">
        <!-- Status Card -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plug me-2"></i>Connection Status</div>
            <div class="card-body">
                <?php if ($isConnected): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Connected to QuickBooks</strong><br>
                        <span class="small">Company ID: <?= e($qbToken['realm_id'] ?? '—') ?></span><br>
                        <span class="small text-muted">Token expires: <?= formatDateTime($qbToken['expires_at'] ?? '') ?></span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= e(buildQbAuthUrl()) ?>" id="qbConnectBtn" class="btn btn-warning">
                        <i class="bi bi-arrow-repeat me-1"></i> Re-connect / Refresh
                    </a>
                    <a href="<?= BASE_URL ?>/quickbooks/disconnect.php" class="btn btn-outline-danger"
                       data-confirm="Disconnect from QuickBooks?">
                        <i class="bi bi-x-circle me-1"></i> Disconnect
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Not Connected</strong><br>
                        <span class="small text-muted">Connect your QuickBooks account to enable automatic invoicing and quoting.</span>
                    </div>
                </div>
                <?php if (QB_CLIENT_ID): ?>
                <a href="<?= e(buildQbAuthUrl()) ?>" id="qbConnectBtn" class="btn btn-quickbooks btn-lg">
                    <i class="bi bi-link-45deg me-2"></i> Connect to QuickBooks
                </a>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Configuration Required:</strong> Please set <code>QB_CLIENT_ID</code> and <code>QB_CLIENT_SECRET</code>
                    in <code>config/config.php</code> before connecting.
                    See setup instructions below.
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Features -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-star me-2"></i>Integration Features</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-3 fs-5"></i>
                        <div>
                            <strong>Automatic Invoice Sync</strong>
                            <div class="small text-muted">Push invoices from the support system directly to QuickBooks.</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-3 fs-5"></i>
                        <div>
                            <strong>Quote / Estimate Sync</strong>
                            <div class="small text-muted">Sync quotes as QuickBooks Estimates.</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-3 fs-5"></i>
                        <div>
                            <strong>Customer Sync</strong>
                            <div class="small text-muted">Clients are automatically created/matched in QuickBooks.</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-3 fs-5"></i>
                        <div>
                            <strong>Payment Status</strong>
                            <div class="small text-muted">Mark invoices as paid in both systems simultaneously.</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Setup Guide -->
        <div class="card">
            <div class="card-header"><i class="bi bi-book me-2"></i>QuickBooks Setup Guide</div>
            <div class="card-body small">
                <ol class="ps-3">
                    <li class="mb-2">
                        <strong>Create a QuickBooks Developer Account</strong><br>
                        Visit <a href="https://developer.intuit.com" target="_blank">developer.intuit.com</a> and sign up.
                    </li>
                    <li class="mb-2">
                        <strong>Create an App</strong><br>
                        Go to <em>Dashboard → Create an App → QuickBooks Online and Payments</em>.
                        Select the <em>Accounting</em> scope.
                    </li>
                    <li class="mb-2">
                        <strong>Set Redirect URI</strong><br>
                        Add this exact URL as a Redirect URI in your app settings:<br>
                        <code class="d-block mt-1 p-1 bg-light rounded"><?= e(QB_REDIRECT_URI) ?></code>
                    </li>
                    <li class="mb-2">
                        <strong>Copy Credentials</strong><br>
                        Copy your <em>Client ID</em> and <em>Client Secret</em> into
                        <code>config/config.php</code>:
                        <pre class="bg-light p-2 rounded mt-1" style="font-size:.75rem">define('QB_CLIENT_ID',     'YOUR_CLIENT_ID');
define('QB_CLIENT_SECRET', 'YOUR_SECRET');</pre>
                    </li>
                    <li class="mb-2">
                        <strong>Change Environment</strong><br>
                        Set <code>QB_ENVIRONMENT</code> to <code>'production'</code> when ready (use <code>'sandbox'</code> for testing).
                    </li>
                    <li>
                        <strong>Connect</strong><br>
                        Click <em>Connect to QuickBooks</em> above and authorise access.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
