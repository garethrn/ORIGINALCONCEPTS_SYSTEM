<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireBillingAccess();

$pageTitle = 'Payments';

$page   = max(1, (int)($_GET['page'] ?? 1));
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['q']      ?? '');

$where  = ['1=1'];
$params = [];

if ($status) {
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
     ORDER BY i.due_date ASC, i.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

// Summary stats
$summary = [
    'total_outstanding' => dbRow("SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE status IN ('sent','overdue')")['n'] ?? 0,
    'total_overdue'     => dbRow("SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE status = 'overdue' OR (status='sent' AND due_date < CURDATE())")['n'] ?? 0,
    'paid_this_month'   => dbRow("SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE status='paid' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())")['n'] ?? 0,
    'draft_total'       => dbRow("SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE status='draft'")['n'] ?? 0,
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2"></i>Payments & Billing Summary</h4>
    <a href="<?= BASE_URL ?>/invoices/create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>New Invoice</a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="card stat-card warning"><div class="card-body">
            <div class="stat-value text-warning" style="font-size:1.3rem"><?= money((float)$summary['total_outstanding']) ?></div>
            <div class="stat-label">Outstanding</div>
        </div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card danger"><div class="card-body">
            <div class="stat-value text-danger" style="font-size:1.3rem"><?= money((float)$summary['total_overdue']) ?></div>
            <div class="stat-label">Overdue</div>
        </div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card success"><div class="card-body">
            <div class="stat-value text-success" style="font-size:1.3rem"><?= money((float)$summary['paid_this_month']) ?></div>
            <div class="stat-label">Paid This Month</div>
        </div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card secondary"><div class="card-body">
            <div class="stat-value" style="font-size:1.3rem"><?= money((float)$summary['draft_total']) ?></div>
            <div class="stat-label">Draft Invoices</div>
        </div></div>
    </div>
</div>

<!-- Filters -->
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
                <a href="<?= BASE_URL ?>/payments/index.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Invoice Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Paid On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No invoices found.</td></tr>
                <?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                <?php $isOverdue = $inv['status'] !== 'paid' && $inv['due_date'] < date('Y-m-d'); ?>
                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                    <td>
                        <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= invoiceRef($inv['id']) ?>
                        </a>
                    </td>
                    <td><?= e($inv['client_name']) ?></td>
                    <td class="fw-semibold"><?= money($inv['total_amount']) ?></td>
                    <td class="small <?= $isOverdue ? 'text-danger fw-bold' : '' ?>"><?= formatDate($inv['due_date']) ?></td>
                    <td><?= invoiceStatusBadge($inv['status']) ?></td>
                    <td class="small text-success"><?= $inv['paid_at'] ? formatDate($inv['paid_at']) : '—' ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a>
                            <?php if (!in_array($inv['status'], ['paid','void'])): ?>
                            <form method="POST" action="<?= BASE_URL ?>/payments/mark_paid.php" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success py-0" data-confirm="Mark <?= invoiceRef($inv['id']) ?> as paid?"
                                        title="Mark as Paid">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
