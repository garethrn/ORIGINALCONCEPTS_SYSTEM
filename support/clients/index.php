<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager', 'technician');

$pageTitle = 'Clients';

$search = sanitize($_GET['q']    ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ? OR c.phone LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$whereStr = implode(' AND ', $where);
$total    = (int)dbRow("SELECT COUNT(*) AS n FROM clients c WHERE {$whereStr}", $params)['n'];
$pager    = paginate($total, $page);
$params[] = $pager['per_page'];
$params[] = $pager['offset'];

$clients = dbRows(
    "SELECT c.*,
            (SELECT COUNT(*) FROM tickets WHERE client_id = c.id) AS ticket_count,
            (SELECT COUNT(*) FROM invoices WHERE client_id = c.id AND status NOT IN ('paid','void')) AS open_invoices
     FROM clients c
     WHERE {$whereStr}
     ORDER BY c.name ASC
     LIMIT ? OFFSET ?",
    $params
);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Clients</h4>
    <a href="<?= BASE_URL ?>/clients/create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Add Client
    </a>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-5">
                <input type="search" name="q" id="liveSearch" class="form-control form-control-sm"
                       placeholder="Search by name, company, email, phone…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/clients/index.php" class="btn btn-sm btn-outline-secondary">Clear</a>
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
                        <th>Name</th>
                        <th>Company</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th class="text-center">Tickets</th>
                        <th class="text-center">Open Inv.</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($clients)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">No clients found.</td></tr>
                <?php endif; ?>
                <?php foreach ($clients as $c): ?>
                <tr class="searchable-row">
                    <td>
                        <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= e($c['name']) ?>
                        </a>
                    </td>
                    <td><?= e($c['company'] ?? '—') ?></td>
                    <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
                    <td><?= e($c['phone'] ?? '—') ?></td>
                    <td class="text-truncate" style="max-width:150px">
                        <?php if ($c['city'] || $c['country']): ?>
                        <i class="bi bi-geo-alt text-muted me-1"></i><?= e(trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ')) ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= BASE_URL ?>/tickets/index.php?client_id=<?= $c['id'] ?>" class="badge bg-primary text-decoration-none">
                            <?= $c['ticket_count'] ?>
                        </a>
                    </td>
                    <td class="text-center">
                        <?php if ($c['open_invoices'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?= $c['open_invoices'] ?></span>
                        <?php else: ?>
                        <span class="badge bg-light text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($c['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm py-0"><i class="bi bi-eye"></i></a>
                            <a href="<?= BASE_URL ?>/clients/edit.php?id=<?= $c['id'] ?>" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-pencil"></i></a>
                        </div>
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
        <?= paginationHtml($pager, BASE_URL . '/clients/index.php?' . http_build_query(array_filter(['q' => $search]))) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
