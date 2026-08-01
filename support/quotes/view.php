<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager');

$id    = (int)($_GET['id'] ?? 0);
$quote = dbRow(
    'SELECT q.*, c.name AS client_name, c.email AS client_email,
            c.company AS client_company, c.address AS client_address,
            c.city AS client_city, c.country AS client_country,
            u.name AS created_by_name
     FROM quotes q
     LEFT JOIN clients c ON q.client_id = c.id
     LEFT JOIN users u ON q.created_by = u.id
     WHERE q.id = ?',
    [$id]
);

if (!$quote) {
    redirectWithMessage(BASE_URL . '/quotes/index.php', 'Quote not found.', 'danger');
}

$pageTitle = quoteRef($id);

// ─── Handle Actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = sanitize($_POST['status'] ?? '');
        $allowed   = ['draft','sent','accepted','rejected','expired'];
        if (in_array($newStatus, $allowed)) {
            dbUpdate('quotes', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);

            // If accepted → create invoice
            if ($newStatus === 'accepted') {
                $lineItems = json_decode($quote['line_items'] ?? '[]', true) ?: [];
                $invoiceId = dbInsert('invoices', [
                    'client_id'    => $quote['client_id'],
                    'ticket_id'    => $quote['ticket_id'],
                    'line_items'   => $quote['line_items'],
                    'subtotal'     => $quote['subtotal'],
                    'tax_rate'     => $quote['tax_rate'],
                    'tax_amount'   => $quote['tax_amount'],
                    'total_amount' => $quote['total_amount'],
                    'notes'        => 'Created from ' . quoteRef($id),
                    'status'       => 'draft',
                    'due_date'     => date('Y-m-d', strtotime('+30 days')),
                    'created_by'   => currentUserId(),
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
                redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $invoiceId, 'Quote accepted. Invoice ' . invoiceRef($invoiceId) . ' created.');
            }
        }
        redirectWithMessage(BASE_URL . '/quotes/view.php?id=' . $id, 'Quote status updated.');
    }

    if ($action === 'send_to_client' && $quote['client_email']) {
        $subject = quoteRef($id) . ' – Quote from ' . COMPANY_NAME;
        $body    = '<p>Dear ' . e($quote['client_name']) . ',</p>
                    <p>Please find your quote below.</p>
                    <table style="border-collapse:collapse;width:100%">
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Quote #</strong></td><td style="padding:6px;border:1px solid #ddd">' . quoteRef($id) . '</td></tr>
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Amount</strong></td><td style="padding:6px;border:1px solid #ddd">' . money($quote['total_amount']) . '</td></tr>
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Valid Until</strong></td><td style="padding:6px;border:1px solid #ddd">' . formatDate($quote['valid_until']) . '</td></tr>
                    </table>
                    <p>To accept this quote, please reply to this email or contact us directly.</p>';
        sendMail($quote['client_email'], $subject, $body, $quote['client_name']);
        dbUpdate('quotes', ['status' => 'sent', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        redirectWithMessage(BASE_URL . '/quotes/view.php?id=' . $id, 'Quote emailed to client.');
    }

    // QuickBooks Estimate sync
    if ($action === 'sync_quickbooks') {
        $qbToken = dbRow('SELECT * FROM qb_tokens ORDER BY id DESC LIMIT 1');
        if ($qbToken) {
            require_once dirname(__DIR__) . '/quickbooks/qb_helper.php';
            $result = syncEstimateToQuickBooks($quote, $qbToken);
            if ($result) {
                redirectWithMessage(BASE_URL . '/quotes/view.php?id=' . $id, 'Quote synced to QuickBooks as Estimate.');
            } else {
                redirectWithMessage(BASE_URL . '/quotes/view.php?id=' . $id, 'Failed to sync to QuickBooks.', 'danger');
            }
        } else {
            redirectWithMessage(BASE_URL . '/quotes/view.php?id=' . $id, 'QuickBooks not connected.', 'warning');
        }
    }
}

// Reload
$quote     = dbRow('SELECT q.*, c.name AS client_name, c.email AS client_email, c.company AS client_company, c.address AS client_address, c.city AS client_city, c.country AS client_country, u.name AS created_by_name FROM quotes q LEFT JOIN clients c ON q.client_id = c.id LEFT JOIN users u ON q.created_by = u.id WHERE q.id = ?', [$id]);
$lineItems = json_decode($quote['line_items'] ?? '[]', true) ?: [];

$statusMap = ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'dark'];
$sc        = $statusMap[$quote['status']] ?? 'secondary';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/quotes/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h4 class="mb-0 fw-bold"><?= quoteRef($id) ?></h4>
    <span class="ms-2 badge bg-<?= $sc ?>"><?= ucfirst($quote['status']) ?></span>
</div>

<div class="row">
    <div class="col-lg-9">
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-6">
                        <h2 class="fw-bold text-primary"><?= COMPANY_NAME ?></h2>
                        <p class="mb-0 small text-muted"><?= COMPANY_ADDRESS ?></p>
                        <p class="mb-0 small text-muted"><?= SITE_EMAIL ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h3 class="fw-bold text-uppercase text-muted">Quote</h3>
                        <table class="ms-auto small">
                            <tr><td class="text-muted pe-2">Quote #:</td><td class="fw-bold"><?= quoteRef($id) ?></td></tr>
                            <tr><td class="text-muted pe-2">Date:</td><td><?= formatDate($quote['created_at']) ?></td></tr>
                            <tr><td class="text-muted pe-2">Valid Until:</td><td class="fw-bold"><?= formatDate($quote['valid_until']) ?></td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="mb-4">
                    <h6 class="text-muted text-uppercase small fw-semibold">Prepared For</h6>
                    <p class="fw-bold mb-1"><?= e($quote['client_name']) ?></p>
                    <?php if ($quote['client_company']): ?><p class="mb-1"><?= e($quote['client_company']) ?></p><?php endif; ?>
                    <?php if ($quote['client_email']): ?><p class="mb-0 small"><a href="mailto:<?= e($quote['client_email']) ?>"><?= e($quote['client_email']) ?></a></p><?php endif; ?>
                </div>

                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Unit Rate</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineItems as $line): ?>
                    <tr>
                        <td><?= e($line['desc']) ?></td>
                        <td class="text-center"><?= number_format($line['qty'], 2) ?></td>
                        <td class="text-end"><?= money($line['rate']) ?></td>
                        <td class="text-end"><?= money($line['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end">Subtotal:</td><td class="text-end"><?= money($quote['subtotal']) ?></td></tr>
                        <tr><td colspan="3" class="text-end">VAT (<?= $quote['tax_rate'] ?>%):</td><td class="text-end"><?= money($quote['tax_amount']) ?></td></tr>
                        <tr class="fw-bold fs-6"><td colspan="3" class="text-end text-primary">TOTAL:</td><td class="text-end text-primary"><?= money($quote['total_amount']) ?></td></tr>
                    </tfoot>
                </table>

                <?php if ($quote['notes']): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong class="small">Notes:</strong>
                    <p class="mb-0 small mt-1"><?= nl2br(e($quote['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sliders me-2"></i>Actions</div>
            <div class="card-body d-grid gap-2">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-printer me-2"></i>Print / PDF
                </button>

                <?php if ($quote['client_email'] && $quote['status'] === 'draft'): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send_to_client">
                    <button type="submit" class="btn btn-info btn-sm w-100 text-start"><i class="bi bi-envelope me-2"></i>Email to Client</button>
                </form>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" class="form-select form-select-sm mb-2">
                        <?php foreach (['draft','sent','accepted','rejected','expired'] as $s): ?>
                        <option value="<?= $s ?>" <?= $quote['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Update Status</button>
                </form>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="sync_quickbooks">
                    <button type="submit" class="btn btn-quickbooks btn-sm w-100">
                        <i class="bi bi-cloud-upload me-2"></i>Sync to QuickBooks
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
