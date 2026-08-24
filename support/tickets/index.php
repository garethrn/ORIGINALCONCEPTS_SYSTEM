<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();

$pageTitle = 'Tickets';

// ─── Filters ──────────────────────────────────────────────────────────────────
$status   = sanitize($_GET['status']   ?? '');
$priority = sanitize($_GET['priority'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$search   = sanitize($_GET['q']        ?? '');
$assignee = (int)($_GET['assigned_to'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

// Clients see only their own tickets
$clientId = hasRole('client') ? currentUserId() : (int)($_GET['client_id'] ?? 0);

// ─── Build Query ──────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($status && array_key_exists($status, TICKET_STATUSES)) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
if ($priority && array_key_exists($priority, TICKET_PRIORITIES)) {
    $where[] = 't.priority = ?';
    $params[] = $priority;
}
if ($category && array_key_exists($category, SERVICE_CATEGORIES)) {
    $where[] = 't.category = ?';
    $params[] = $category;
}
if ($clientId) {
    $where[] = 't.client_id = ?';
    $params[] = $clientId;
}
if ($assignee) {
    $where[] = 't.assigned_to = ?';
    $params[] = $assignee;
}
if ($search) {
    $where[] = '(t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereStr = implode(' AND ', $where);

$total  = (int)dbRow("SELECT COUNT(*) AS n FROM tickets t LEFT JOIN clients c ON t.client_id = c.id WHERE {$whereStr}", $params)['n'];
$pager  = paginate($total, $page);
$params[] = $pager['per_page'];
$params[] = $pager['offset'];

$tickets = dbRows(
    "SELECT t.*, c.name AS client_name, c.company AS client_company,
            u.name AS assignee_name
     FROM tickets t
     LEFT JOIN clients c ON t.client_id = c.id
     LEFT JOIN users u ON t.assigned_to = u.id
     WHERE {$whereStr}
     ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at DESC
     LIMIT ? OFFSET ?",
    $params
);

$technicians = dbRows("SELECT id, name FROM users WHERE role IN ('admin','manager','technician') AND is_active = 1 ORDER BY name");

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-ticket-detailed me-2"></i>Support Tickets</h4>
    <a href="<?= BASE_URL ?>/tickets/create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Ticket
    </a>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-4 col-lg-3">
                <input type="search" name="q" class="form-control form-control-sm"
                       placeholder="Search tickets…" value="<?= e($search) ?>">
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (TICKET_STATUSES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All Priorities</option>
                    <?php foreach (TICKET_PRIORITIES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $priority === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Services</option>
                    <?php foreach (SERVICE_CATEGORIES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $category === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (hasRole('admin','manager')): ?>
            <div class="col-auto">
                <select name="assigned_to" class="form-select form-select-sm">
                    <option value="">All Technicians</option>
                    <?php foreach ($technicians as $tech): ?>
                    <option value="<?= $tech['id'] ?>" <?= $assignee === (int)$tech['id'] ? 'selected' : '' ?>>
                        <?= e($tech['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/tickets/index.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Ticket Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ref #</th>
                        <th>Subject</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">No tickets found.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $t): ?>
                <tr class="searchable-row">
                    <td>
                        <a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= ticketRef($t['id']) ?>
                        </a>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="text-decoration-none text-dark">
                            <?= e($t['title']) ?>
                        </a>
                        <?php if (!empty($t['attachment_path'])): ?>
                        <i class="bi bi-paperclip text-muted small" title="Has attachment"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['client_id']): ?>
                        <a href="<?= BASE_URL ?>/clients/view.php?id=<?= $t['client_id'] ?>" class="text-decoration-none">
                            <?= e($t['client_name']) ?>
                        </a>
                        <?php if ($t['client_company']): ?>
                        <div class="text-muted small"><?= e($t['client_company']) ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e(serviceCategoryLabel($t['category'])) ?></span></td>
                    <td><?= ticketPriorityBadge($t['priority']) ?></td>
                    <td><?= ticketStatusBadge($t['status']) ?></td>
                    <td class="small"><?= e($t['assignee_name'] ?? '—') ?></td>
                    <td class="text-muted small"><?= timeAgo($t['created_at']) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-1">
                            <i class="bi bi-eye"></i>
                        </a>
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
        <?= paginationHtml($pager, BASE_URL . '/tickets/index.php?' . http_build_query(array_filter(['q'=>$search,'status'=>$status,'priority'=>$priority,'category'=>$category,'assigned_to'=>$assignee]))) ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
