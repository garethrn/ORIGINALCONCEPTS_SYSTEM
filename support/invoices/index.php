<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireBillingAccess();

$pageTitle = 'Invoices';
$status    = sanitize($_GET['status'] ?? '');
$search    = sanitize($_GET['q']      ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($status && array_key_exists($status, INVOICE_STATUSES)) {
    $where[] = 'i.status = ?';
    $params[] = $status;
}
if ($search) {
    $where[] = '(c.name LIKE ? OR c.company LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like]);
}

$whereStr = implode(' AND ', $where);
$total    = (int)dbRow("SELECT COUNT(*) AS n FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE {$whereStr}", $params)['n'];
$pager    = paginate($total, $page);
$params[] = $pager['per_page'];
$params[] = $pager['offset'];

$invoices = dbRows(
    "SELECT i.*, c.name AS client_name, c.company AS client_company
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     WHERE {$whereStr}
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

// Check for overdue invoices and update status
dbQuery(
    "UPDATE invoices SET status='overdue', updated_at=NOW()
     WHERE status='sent' AND due_date < CURDATE()"
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Invoices</h4>
    <a href="<?= BASE_URL ?>/invoices/create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Invoice
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2">
            <div class="col-sm-4">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search client…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (INVOICE_STATUSES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/invoices/index.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Ticket</th>
                        <th>Amount</th>
                        <th>Tax</th>
                        <th>Total</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>QB Synced</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="11" class="text-center text-muted py-5">No invoices found.</td></tr>
                <?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="fw-semibold text-decoration-none"><?= invoiceRef($inv['id']) ?></a></td>
                    <td>
                        <?= e($inv['client_name']) ?>
                        <?php if ($inv['client_company']): ?><div class="text-muted small"><?= e($inv['client_company']) ?></div><?php endif; ?>
                    </td>
                    <td><?= $inv['ticket_id'] ? '<a href="' . BASE_URL . '/tickets/view.php?id=' . $inv['ticket_id'] . '" class="text-decoration-none">' . ticketRef($inv['ticket_id']) . '</a>' : '—' ?></td>
                    <td><?= money($inv['subtotal']) ?></td>
                    <td><?= money($inv['tax_amount']) ?></td>
                    <td class="fw-semibold"><?= money($inv['total_amount']) ?></td>
                    <td class="small"><?= formatDate($inv['created_at']) ?></td>
                    <td class="small <?= $inv['status'] !== 'paid' && $inv['due_date'] < date('Y-m-d') ? 'text-danger fw-semibold' : '' ?>"><?= formatDate($inv['due_date']) ?></td>
                    <td><?= invoiceStatusBadge($inv['status']) ?></td>
                    <td><?= $inv['qb_invoice_id'] ? '<span class="badge bg-success">Synced</span>' : '<span class="badge bg-light text-muted">No</span>' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pager['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $pager['per_page'], $total) ?> of <?= $total ?></span>
        <?= paginationHtml($pager, BASE_URL . '/invoices/index.php?' . http_build_query(array_filter(['q' => $search, 'status' => $status]))) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
