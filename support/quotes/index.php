<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireBillingAccess();

$pageTitle = 'Quotes';
$page      = max(1, (int)($_GET['page'] ?? 1));
$search    = sanitize($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(c.name LIKE ? OR c.company LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like]);
}

$whereStr = implode(' AND ', $where);
$total    = (int)dbRow("SELECT COUNT(*) AS n FROM quotes q LEFT JOIN clients c ON q.client_id = c.id WHERE {$whereStr}", $params)['n'];
$pager    = paginate($total, $page);
$params[] = $pager['per_page'];
$params[] = $pager['offset'];

$quotes = dbRows(
    "SELECT q.*, c.name AS client_name, c.company AS client_company
     FROM quotes q
     LEFT JOIN clients c ON q.client_id = c.id
     WHERE {$whereStr}
     ORDER BY q.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-ruled me-2"></i>Quotes</h4>
    <a href="<?= BASE_URL ?>/quotes/create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Quote
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Quote #</th><th>Client</th><th>Total</th><th>Valid Until</th><th>Status</th><th>Created</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($quotes)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No quotes found.</td></tr>
                <?php endif; ?>
                <?php foreach ($quotes as $q): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/quotes/view.php?id=<?= $q['id'] ?>" class="fw-semibold text-decoration-none"><?= quoteRef($q['id']) ?></a></td>
                    <td><?= e($q['client_name']) ?><?= $q['client_company'] ? '<br><span class="text-muted small">' . e($q['client_company']) . '</span>' : '' ?></td>
                    <td class="fw-semibold"><?= money($q['total_amount']) ?></td>
                    <td class="small"><?= formatDate($q['valid_until']) ?></td>
                    <td>
                        <?php
                        $statusMap = ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'dark'];
                        $sc = $statusMap[$q['status']] ?? 'secondary';
                        echo '<span class="badge bg-' . $sc . '">' . ucfirst(e($q['status'])) . '</span>';
                        ?>
                    </td>
                    <td class="small text-muted"><?= timeAgo($q['created_at']) ?></td>
                    <td><a href="<?= BASE_URL ?>/quotes/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
