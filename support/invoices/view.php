<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager');

$id      = (int)($_GET['id'] ?? 0);
$invoice = dbRow(
    'SELECT i.*, c.name AS client_name, c.email AS client_email,
            c.company AS client_company, c.address AS client_address,
            c.city AS client_city, c.country AS client_country, c.phone AS client_phone,
            u.name AS created_by_name
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     LEFT JOIN users u ON i.created_by = u.id
     WHERE i.id = ?',
    [$id]
);

if (!$invoice) {
    redirectWithMessage(BASE_URL . '/invoices/index.php', 'Invoice not found.', 'danger');
}

$pageTitle = invoiceRef($id);

// ─── Handle Actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = sanitize($_POST['status'] ?? '');
        if (array_key_exists($newStatus, INVOICE_STATUSES)) {
            $upd = ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
            if ($newStatus === 'paid' && empty($invoice['paid_at'])) {
                $upd['paid_at'] = date('Y-m-d H:i:s');
            }
            dbUpdate('invoices', $upd, ['id' => $id]);
            redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'Invoice status updated.');
        }
    }

    if ($action === 'mark_paid') {
        dbUpdate('invoices', [
            'status'     => 'paid',
            'paid_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
        redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'Invoice marked as paid.');
    }

    if ($action === 'send_to_client' && $invoice['client_email']) {
        $subject = invoiceRef($id) . ' – Invoice from ' . COMPANY_NAME;
        $body    = '<p>Dear ' . e($invoice['client_name']) . ',</p>
                    <p>Please find your invoice attached.</p>
                    <table style="border-collapse:collapse;width:100%">
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Invoice #</strong></td><td style="padding:6px;border:1px solid #ddd">' . invoiceRef($id) . '</td></tr>
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Amount</strong></td><td style="padding:6px;border:1px solid #ddd">' . money($invoice['total_amount']) . '</td></tr>
                        <tr><td style="padding:6px;border:1px solid #ddd"><strong>Due Date</strong></td><td style="padding:6px;border:1px solid #ddd">' . formatDate($invoice['due_date']) . '</td></tr>
                    </table>
                    <p>Please make payment by the due date. If you have any queries, contact us at ' . SITE_EMAIL . '.</p>
                    <p><a href="' . SITE_URL . '/invoices/view.php?id=' . $id . '" style="background:#1a3c5e;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px;">View Invoice Online</a></p>';
        sendMail($invoice['client_email'], $subject, $body, $invoice['client_name']);
        dbUpdate('invoices', ['status' => 'sent', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'Invoice emailed to client and marked as Sent.');
    }

    // QuickBooks sync
    if ($action === 'sync_quickbooks') {
        $qbToken = dbRow('SELECT * FROM qb_tokens ORDER BY id DESC LIMIT 1');
        if ($qbToken) {
            require_once dirname(__DIR__) . '/quickbooks/qb_helper.php';
            $result = syncInvoiceToQuickBooks($invoice, $qbToken);
            if ($result) {
                redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'Invoice synced to QuickBooks successfully.');
            } else {
                redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'Failed to sync to QuickBooks. Check QB connection.', 'danger');
            }
        } else {
            redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $id, 'QuickBooks not connected. Please connect first.', 'warning');
        }
    }
}

// Reload
$invoice   = dbRow('SELECT i.*, c.name AS client_name, c.email AS client_email, c.company AS client_company, c.address AS client_address, c.city AS client_city, c.country AS client_country, c.phone AS client_phone, u.name AS created_by_name FROM invoices i LEFT JOIN clients c ON i.client_id = c.id LEFT JOIN users u ON i.created_by = u.id WHERE i.id = ?', [$id]);
$lineItems = json_decode($invoice['line_items'] ?? '[]', true) ?: [];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h4 class="mb-0 fw-bold"><?= invoiceRef($id) ?></h4>
    <span class="ms-2"><?= invoiceStatusBadge($invoice['status']) ?></span>
</div>

<div class="row">
    <div class="col-lg-9">
        <!-- Printable Invoice -->
        <div class="card mb-3" id="invoiceDoc">
            <div class="card-body p-4">
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col-6">
                        <h2 class="fw-bold text-primary"><?= COMPANY_NAME ?></h2>
                        <p class="mb-0 small text-muted"><?= COMPANY_ADDRESS ?></p>
                        <p class="mb-0 small text-muted"><?= SITE_EMAIL ?></p>
                        <p class="mb-0 small text-muted"><?= COMPANY_PHONE ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h3 class="fw-bold text-uppercase text-muted">Invoice</h3>
                        <table class="ms-auto small">
                            <tr><td class="text-muted pe-2">Invoice #:</td><td class="fw-bold"><?= invoiceRef($id) ?></td></tr>
                            <tr><td class="text-muted pe-2">Date:</td><td><?= formatDate($invoice['created_at']) ?></td></tr>
                            <tr><td class="text-muted pe-2">Due Date:</td><td class="fw-bold <?= $invoice['status'] !== 'paid' && $invoice['due_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= formatDate($invoice['due_date']) ?></td></tr>
                        </table>
                    </div>
                </div>

                <hr>

                <!-- Bill To -->
                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="text-muted text-uppercase small fw-semibold">Bill To</h6>
                        <p class="fw-bold mb-1"><?= e($invoice['client_name']) ?></p>
                        <?php if ($invoice['client_company']): ?><p class="mb-1"><?= e($invoice['client_company']) ?></p><?php endif; ?>
                        <?php if ($invoice['client_address']): ?><p class="mb-1 small text-muted"><?= e($invoice['client_address']) ?></p><?php endif; ?>
                        <?php if ($invoice['client_city']): ?><p class="mb-1 small text-muted"><?= e($invoice['client_city']) ?><?= $invoice['client_country'] ? ', ' . e($invoice['client_country']) : '' ?></p><?php endif; ?>
                        <?php if ($invoice['client_email']): ?><p class="mb-0 small"><a href="mailto:<?= e($invoice['client_email']) ?>"><?= e($invoice['client_email']) ?></a></p><?php endif; ?>
                    </div>
                    <?php if ($invoice['ticket_id']): ?>
                    <div class="col-6 text-end">
                        <h6 class="text-muted text-uppercase small fw-semibold">Related Ticket</h6>
                        <a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $invoice['ticket_id'] ?>" class="fw-bold"><?= ticketRef($invoice['ticket_id']) ?></a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Line Items -->
                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Description</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Rate</th>
                            <th class="text-end">Amount</th>
                        </tr>
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
                        <tr><td colspan="3" class="text-end">Subtotal:</td><td class="text-end"><?= money($invoice['subtotal']) ?></td></tr>
                        <tr><td colspan="3" class="text-end">VAT (<?= $invoice['tax_rate'] ?>%):</td><td class="text-end"><?= money($invoice['tax_amount']) ?></td></tr>
                        <tr class="fw-bold fs-6"><td colspan="3" class="text-end text-primary">TOTAL DUE:</td><td class="text-end text-primary"><?= money($invoice['total_amount']) ?></td></tr>
                        <?php if ($invoice['status'] === 'paid'): ?>
                        <tr><td colspan="4" class="text-center text-success fw-bold py-2">✓ PAID <?= $invoice['paid_at'] ? 'on ' . formatDate($invoice['paid_at']) : '' ?></td></tr>
                        <?php endif; ?>
                    </tfoot>
                </table>

                <?php if ($invoice['notes']): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong class="small">Notes & Terms:</strong>
                    <p class="mb-0 small mt-1"><?= nl2br(e($invoice['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions Sidebar -->
    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sliders me-2"></i>Actions</div>
            <div class="card-body d-grid gap-2">
                <!-- Print -->
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-printer me-2"></i>Print / Save PDF
                </button>

                <!-- Send to Client -->
                <?php if ($invoice['client_email'] && $invoice['status'] === 'draft'): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send_to_client">
                    <button type="submit" class="btn btn-info btn-sm text-start w-100" data-confirm="Send this invoice to <?= e($invoice['client_email']) ?>?">
                        <i class="bi bi-envelope me-2"></i>Email to Client
                    </button>
                </form>
                <?php endif; ?>

                <!-- Mark Paid -->
                <?php if (!in_array($invoice['status'], ['paid','void'])): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_paid">
                    <button type="submit" class="btn btn-success btn-sm text-start w-100" data-confirm="Mark this invoice as paid?">
                        <i class="bi bi-check-circle me-2"></i>Mark as Paid
                    </button>
                </form>
                <?php endif; ?>

                <!-- Update Status -->
                <form method="POST" class="mt-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" class="form-select form-select-sm mb-2">
                        <?php foreach (INVOICE_STATUSES as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $invoice['status'] === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">Update Status</button>
                </form>

                <!-- QuickBooks Sync -->
                <form method="POST" class="mt-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="sync_quickbooks">
                    <button type="submit" class="btn btn-quickbooks btn-sm w-100">
                        <i class="bi bi-cloud-upload me-2"></i>
                        <?= $invoice['qb_invoice_id'] ? 'Re-sync QuickBooks' : 'Sync to QuickBooks' ?>
                    </button>
                </form>
                <?php if ($invoice['qb_invoice_id']): ?>
                <p class="text-muted small text-center mb-0">QB ID: <?= e($invoice['qb_invoice_id']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Invoice Info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created By</span><span><?= e($invoice['created_by_name'] ?? '—') ?></span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Created</span><span><?= formatDate($invoice['created_at']) ?></span></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Due</span><span><?= formatDate($invoice['due_date']) ?></span></div>
                <?php if ($invoice['paid_at']): ?>
                <div class="d-flex justify-content-between mb-0"><span class="text-muted">Paid</span><span class="text-success fw-semibold"><?= formatDate($invoice['paid_at']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
