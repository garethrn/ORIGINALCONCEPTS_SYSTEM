<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireBillingAccess();

$pageTitle = 'Create Invoice';
$errors    = [];

$clients    = dbRows("SELECT id, name, company FROM clients WHERE is_active = 1 ORDER BY name");
$ticketId   = (int)($_GET['ticket_id'] ?? 0);
$clientIdPre = (int)($_GET['client_id'] ?? 0);

$prefillTicket = $ticketId ? dbRow('SELECT * FROM tickets WHERE id = ?', [$ticketId]) : null;
$vatRate       = max(0, (float)appSetting('invoice_default_tax_rate', '15'));
$defaultDueDays = max(1, (int)appSetting('invoice_default_due_days', '30'));

$formData = [
    'client_id'  => $clientIdPre ?: ($prefillTicket['client_id'] ?? 0),
    'ticket_id'  => $ticketId,
    'notes'      => $prefillTicket ? 'Service: ' . ($prefillTicket['title'] ?? '') : '',
    'due_days'   => $defaultDueDays,
    'tax_rate'   => $vatRate,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $clientId  = (int)($_POST['client_id']  ?? 0);
        $ticketRef = (int)($_POST['ticket_id']  ?? 0);
        $notes     = sanitize($_POST['notes']   ?? '');
        $taxRate   = max(0, (float)($_POST['tax_rate'] ?? $vatRate));
        $dueDays   = max(1, (int)($_POST['due_days']   ?? $defaultDueDays));

        $formData  = compact('clientId', 'notes', 'taxRate', 'dueDays');
        $formData['client_id'] = $clientId;
        $formData['ticket_id'] = $ticketRef;
        $formData['tax_rate']  = $taxRate;
        $formData['due_days']  = $dueDays;

        // Line items
        $lineDescs = $_POST['line_desc']   ?? [];
        $lineQtys  = $_POST['line_qty']    ?? [];
        $lineRates = $_POST['line_rate']   ?? [];

        $lineItems = [];
        $subtotal  = 0.0;

        foreach ($lineDescs as $i => $desc) {
            $desc = sanitize($desc);
            $qty  = max(0, (float)($lineQtys[$i]  ?? 0));
            $rate = max(0, (float)($lineRates[$i] ?? 0));
            if ($desc && $qty > 0) {
                $lineTotal   = $qty * $rate;
                $subtotal   += $lineTotal;
                $lineItems[] = ['desc' => $desc, 'qty' => $qty, 'rate' => $rate, 'total' => $lineTotal];
            }
        }

        if (!$clientId)         $errors[] = 'Client is required.';
        if (empty($lineItems))  $errors[] = 'At least one line item is required.';

        if (empty($errors)) {
            $taxAmount   = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;
            $dueDate     = date('Y-m-d', strtotime("+{$dueDays} days"));

            $invoiceId = dbInsert('invoices', [
                'client_id'    => $clientId,
                'ticket_id'    => $ticketRef ?: null,
                'line_items'   => json_encode($lineItems),
                'subtotal'     => $subtotal,
                'tax_rate'     => $taxRate,
                'tax_amount'   => $taxAmount,
                'total_amount' => $totalAmount,
                'notes'        => $notes,
                'status'       => 'draft',
                'due_date'     => $dueDate,
                'created_by'   => currentUserId(),
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $invoiceId, 'Invoice ' . invoiceRef($invoiceId) . ' created successfully.');
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-plus me-2"></i>Create Invoice</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <!-- Client & Ticket -->
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-2"></i>Invoice Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-select" required>
                                <option value="">— Select Client —</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($formData['client_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?><?= $c['company'] ? ' – ' . e($c['company']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Related Ticket (optional)</label>
                            <input type="number" name="ticket_id" class="form-control" value="<?= $formData['ticket_id'] ?? '' ?>" min="1" placeholder="Ticket ID">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Payment Terms (days)</label>
                            <input type="number" name="due_days" class="form-control" value="<?= $formData['due_days'] ?? 30 ?>" min="1" max="365">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">VAT Rate (%)</label>
                            <input type="number" name="tax_rate" class="form-control" value="<?= $formData['tax_rate'] ?? 15 ?>" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes / Terms</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Payment terms, bank details, notes…"><?= e($formData['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul me-2"></i>Line Items</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                        <i class="bi bi-plus"></i> Add Line
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="lineItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Description</th>
                                <th style="width:90px">Qty</th>
                                <th style="width:120px">Rate (<?= CURRENCY_SYMBOL ?>)</th>
                                <th style="width:120px">Total</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="lineItems">
                            <tr class="line-row">
                                <td class="ps-3"><input type="text" name="line_desc[]" class="form-control form-control-sm" placeholder="Description of service/product" required></td>
                                <td><input type="number" name="line_qty[]" class="form-control form-control-sm line-qty" value="1" min="0.01" step="0.01" required></td>
                                <td><input type="number" name="line_rate[]" class="form-control form-control-sm line-rate" value="0.00" min="0" step="0.01" required></td>
                                <td><span class="line-total small fw-semibold">R 0.00</span></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger py-0 remove-line"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="3" class="text-end fw-semibold ps-3">Subtotal:</td>
                                <td><span id="subtotalDisplay">R 0.00</span></td>
                                <td></td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="3" class="text-end fw-semibold ps-3">VAT (<span id="vatRateDisplay">15</span>%):</td>
                                <td><span id="vatDisplay">R 0.00</span></td>
                                <td></td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td colspan="3" class="text-end ps-3">TOTAL:</td>
                                <td><span id="totalDisplay" class="text-primary fs-6">R 0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i> Create Invoice
            </button>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-info-circle me-2"></i>Information</div>
                <div class="card-body small text-muted">
                    <ul class="ps-3 mb-0">
                        <li class="mb-1">Invoices are created as <strong>Draft</strong> – change to <strong>Sent</strong> when emailed to client.</li>
                        <li class="mb-1">VAT defaults to <strong><?= e((string)$vatRate) ?>%</strong> (adjust in Admin Settings).</li>
                        <li class="mb-1">Invoices can be synced to <strong>QuickBooks</strong> from the invoice view.</li>
                        <li>Payment terms are calculated from today's date.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$symbol = CURRENCY_SYMBOL;
$extraScripts = <<<JS
<script>
var vatRate = parseFloat(document.querySelector('[name="tax_rate"]').value) || 15;

document.querySelector('[name="tax_rate"]').addEventListener('change', function () {
    vatRate = parseFloat(this.value) || 0;
    document.getElementById('vatRateDisplay').textContent = vatRate;
    recalc();
});

document.getElementById('addLineBtn').addEventListener('click', addLine);

function addLine() {
    var tbody = document.getElementById('lineItems');
    var row   = tbody.querySelector('.line-row').cloneNode(true);
    row.querySelectorAll('input').forEach(function (inp) {
        inp.value = inp.name.includes('qty') ? '1' : (inp.name.includes('rate') ? '0.00' : '');
    });
    row.querySelector('.line-total').textContent = '{$symbol} 0.00';
    tbody.appendChild(row);
    attachRowListeners(row);
}

document.querySelectorAll('.line-row').forEach(attachRowListeners);

function attachRowListeners(row) {
    row.querySelectorAll('.line-qty, .line-rate').forEach(function (inp) {
        inp.addEventListener('input', function () { calcRow(row); recalc(); });
    });
    var removeBtn = row.querySelector('.remove-line');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            if (document.querySelectorAll('.line-row').length > 1) { row.remove(); recalc(); }
        });
    }
    calcRow(row);
}

function calcRow(row) {
    var qty   = parseFloat(row.querySelector('.line-qty').value)  || 0;
    var rate  = parseFloat(row.querySelector('.line-rate').value) || 0;
    var total = qty * rate;
    row.querySelector('.line-total').textContent = '{$symbol} ' + total.toFixed(2);
}

function recalc() {
    var subtotal = 0;
    document.querySelectorAll('.line-row').forEach(function (row) {
        var qty  = parseFloat(row.querySelector('.line-qty').value)  || 0;
        var rate = parseFloat(row.querySelector('.line-rate').value) || 0;
        subtotal += qty * rate;
    });
    var vat   = subtotal * (vatRate / 100);
    var total = subtotal + vat;
    document.getElementById('subtotalDisplay').textContent = '{$symbol} ' + subtotal.toFixed(2);
    document.getElementById('vatDisplay').textContent      = '{$symbol} ' + vat.toFixed(2);
    document.getElementById('totalDisplay').textContent    = '{$symbol} ' + total.toFixed(2);
}
recalc();
</script>
JS;

require_once dirname(__DIR__) . '/includes/footer.php';
?>
