<?php
define('OC_SYSTEM', true);
require_once __DIR__ . '/includes/bootstrap.php';

requireLogin();

$pageTitle = 'Dashboard';
$user      = currentUser();

// Guard: if the user record cannot be loaded (e.g. DB misconfigured) log out
// cleanly so the admin sees the login page rather than a blank crash.
if (!$user) {
    logout();
    redirect(BASE_URL . '/auth/login.php?error=session');
}

// ─── Dashboard Stats ──────────────────────────────────────────────────────────
$stats = [
    'tickets_open'     => dbRow('SELECT COUNT(*) AS n FROM tickets WHERE status IN (\'open\',\'in_progress\',\'waiting\')')['n'] ?? 0,
    'tickets_today'    => dbRow('SELECT COUNT(*) AS n FROM tickets WHERE DATE(created_at) = CURDATE()')['n'] ?? 0,
    'tickets_resolved' => dbRow('SELECT COUNT(*) AS n FROM tickets WHERE status = \'resolved\' AND DATE(updated_at) = CURDATE()')['n'] ?? 0,
    'clients_total'    => dbRow('SELECT COUNT(*) AS n FROM clients')['n'] ?? 0,
    'invoices_unpaid'  => dbRow('SELECT COUNT(*) AS n FROM invoices WHERE status IN (\'sent\',\'overdue\')')['n'] ?? 0,
    'revenue_month'    => dbRow('SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE status = \'paid\' AND MONTH(paid_at) = MONTH(CURDATE()) AND YEAR(paid_at) = YEAR(CURDATE())')['n'] ?? 0,
];

// ─── Recent Tickets ───────────────────────────────────────────────────────────
$recentTickets = dbRows(
    'SELECT t.*, c.name AS client_name, c.company AS client_company
     FROM tickets t
     LEFT JOIN clients c ON t.client_id = c.id
     ORDER BY t.created_at DESC
     LIMIT 10'
);

// ─── My Open Tickets (if technician) ─────────────────────────────────────────
$myTickets = [];
if (hasRole('technician')) {
    $myTickets = dbRows(
        'SELECT t.*, c.name AS client_name
         FROM tickets t
         LEFT JOIN clients c ON t.client_id = c.id
         WHERE t.assigned_to = ? AND t.status NOT IN (\'closed\',\'cancelled\')
         ORDER BY t.priority DESC, t.created_at DESC
         LIMIT 10',
        [currentUserId()]
    );
}

// ─── Overdue Invoices ─────────────────────────────────────────────────────────
$overdueInvoices = [];
if (userCanAccessBilling()) {
    $overdueInvoices = dbRows(
        'SELECT i.*, c.name AS client_name
         FROM invoices i
         LEFT JOIN clients c ON i.client_id = c.id
         WHERE i.status = \'overdue\' OR (i.status = \'sent\' AND i.due_date < CURDATE())
         ORDER BY i.due_date ASC
         LIMIT 5'
    );
}

// ─── Tickets by Category ─────────────────────────────────────────────────────
$byCat = dbRows('SELECT category, COUNT(*) AS cnt FROM tickets WHERE status NOT IN (\'closed\',\'cancelled\') GROUP BY category ORDER BY cnt DESC');

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold">Dashboard</h4>
        <span class="text-muted small">Welcome back, <?= e($user['name']) ?>! &nbsp;<?= date('l, d F Y') ?></span>
    </div>
    <a href="<?= BASE_URL ?>/tickets/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Ticket
    </a>
</div>

<!-- ─── Stat Row ────────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card primary h-100">
            <div class="card-body">
                <div class="stat-value text-primary"><?= $stats['tickets_open'] ?></div>
                <div class="stat-label mt-1">Open Tickets</div>
                <i class="bi bi-ticket-detailed text-primary opacity-25 fs-2 position-absolute end-0 bottom-0 me-2 mb-1"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card info h-100">
            <div class="card-body">
                <div class="stat-value text-info"><?= $stats['tickets_today'] ?></div>
                <div class="stat-label mt-1">Today's Tickets</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="stat-value text-success"><?= $stats['tickets_resolved'] ?></div>
                <div class="stat-label mt-1">Resolved Today</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card secondary h-100">
            <div class="card-body">
                <div class="stat-value"><?= $stats['clients_total'] ?></div>
                <div class="stat-label mt-1">Total Clients</div>
            </div>
        </div>
    </div>
    <?php if (userCanAccessBilling()): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card warning h-100">
            <div class="card-body">
                <div class="stat-value text-warning"><?= $stats['invoices_unpaid'] ?></div>
                <div class="stat-label mt-1">Unpaid Invoices</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card stat-card success h-100">
            <div class="card-body">
                <div class="stat-value text-success" style="font-size:1.4rem"><?= money((float)$stats['revenue_month']) ?></div>
                <div class="stat-label mt-1">Revenue This Month</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- ─── Recent Tickets ──────────────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-ticket-detailed me-2"></i>Recent Tickets</span>
                <a href="<?= BASE_URL ?>/tickets/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ref</th>
                                <th>Subject</th>
                                <th>Client</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentTickets)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No tickets yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentTickets as $t): ?>
                        <tr>
                            <td><a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="fw-semibold text-decoration-none"><?= ticketRef($t['id']) ?></a></td>
                            <td class="text-truncate" style="max-width:200px"><?= e($t['title']) ?></td>
                            <td><?= e($t['client_name'] ?? 'N/A') ?></td>
                            <td><?= ticketPriorityBadge($t['priority']) ?></td>
                            <td><?= ticketStatusBadge($t['status']) ?></td>
                            <td class="text-muted small"><?= timeAgo($t['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Right Sidebar ──────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Quick Actions -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>/tickets/create.php" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-plus-circle me-2"></i>Create Ticket
                </a>
                <?php if (userCanManageClients()): ?>
                <a href="<?= BASE_URL ?>/clients/create.php" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-person-plus me-2"></i>Add Client
                </a>
                <?php endif; ?>
                <?php if (userCanAccessBilling()): ?>
                <a href="<?= BASE_URL ?>/invoices/create.php" class="btn btn-outline-success btn-sm text-start">
                    <i class="bi bi-file-earmark-plus me-2"></i>New Invoice
                </a>
                <a href="<?= BASE_URL ?>/quotes/create.php" class="btn btn-outline-info btn-sm text-start">
                    <i class="bi bi-file-earmark-ruled me-2"></i>New Quote
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tickets by Category -->
        <?php if (!empty($byCat)): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Open Tickets by Service</div>
            <div class="card-body p-2">
                <?php foreach ($byCat as $row): ?>
                <div class="d-flex justify-content-between align-items-center px-2 py-1">
                    <span class="small"><?= e(serviceCategoryLabel($row['category'])) ?></span>
                    <span class="badge bg-primary rounded-pill"><?= $row['cnt'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Overdue Invoices -->
        <?php if (!empty($overdueInvoices)): ?>
        <div class="card border-danger">
            <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Overdue Invoices</div>
            <ul class="list-group list-group-flush">
            <?php foreach ($overdueInvoices as $inv): ?>
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="fw-semibold text-decoration-none small">
                            <?= invoiceRef($inv['id']) ?>
                        </a>
                        <span class="text-danger small fw-semibold"><?= money($inv['total_amount']) ?></span>
                    </div>
                    <div class="text-muted small"><?= e($inv['client_name']) ?> &ndash; Due <?= formatDate($inv['due_date']) ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- My Tickets (Technician) -->
        <?php if (!empty($myTickets)): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-person-check me-2"></i>My Open Tickets</div>
            <ul class="list-group list-group-flush">
            <?php foreach ($myTickets as $t): ?>
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="fw-semibold small text-decoration-none"><?= ticketRef($t['id']) ?></a>
                        <?= ticketPriorityBadge($t['priority']) ?>
                    </div>
                    <div class="text-truncate small text-muted"><?= e($t['title']) ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
