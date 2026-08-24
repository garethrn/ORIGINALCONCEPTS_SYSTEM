<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireClientManagementAccess();

$id     = (int)($_GET['id'] ?? 0);
$client = dbRow('SELECT * FROM clients WHERE id = ?', [$id]);

if (!$client) {
    redirectWithMessage(BASE_URL . '/clients/index.php', 'Client not found.', 'danger');
}

$pageTitle = 'Client: ' . $client['name'];

// Ticket stats
$tickets  = dbRows('SELECT t.*, u.name AS assignee_name FROM tickets t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.client_id = ? ORDER BY t.created_at DESC LIMIT 20', [$id]);
$invoices = dbRows('SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC LIMIT 10', [$id]);
$quotes   = dbRows('SELECT * FROM quotes WHERE client_id = ? ORDER BY created_at DESC LIMIT 10', [$id]);

$stats = [
    'open'    => dbRow('SELECT COUNT(*) AS n FROM tickets WHERE client_id = ? AND status IN (\'open\',\'in_progress\',\'waiting\')', [$id])['n'] ?? 0,
    'closed'  => dbRow('SELECT COUNT(*) AS n FROM tickets WHERE client_id = ? AND status IN (\'closed\',\'resolved\')', [$id])['n'] ?? 0,
    'unpaid'  => dbRow('SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE client_id = ? AND status IN (\'sent\',\'overdue\')', [$id])['n'] ?? 0,
    'revenue' => dbRow('SELECT COALESCE(SUM(total_amount),0) AS n FROM invoices WHERE client_id = ? AND status = \'paid\'', [$id])['n'] ?? 0,
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/clients/index.php">Clients</a></li>
        <li class="breadcrumb-item active"><?= e($client['name']) ?></li>
    </ol>
</nav>

<!-- Client Header -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="avatar-circle" style="width:60px;height:60px;font-size:1.5rem">
                    <?= strtoupper(substr($client['name'], 0, 1)) ?>
                </div>
            </div>
            <div class="col">
                <h4 class="mb-1 fw-bold"><?= e($client['name']) ?></h4>
                <?php if ($client['company']): ?><p class="mb-1 text-muted"><i class="bi bi-building me-1"></i><?= e($client['company']) ?></p><?php endif; ?>
                <div class="d-flex flex-wrap gap-3 small">
                    <?php if ($client['email']): ?><span><i class="bi bi-envelope me-1 text-primary"></i><a href="mailto:<?= e($client['email']) ?>"><?= e($client['email']) ?></a></span><?php endif; ?>
                    <?php if ($client['phone']): ?><span><i class="bi bi-telephone me-1 text-success"></i><a href="tel:<?= e($client['phone']) ?>"><?= e($client['phone']) ?></a></span><?php endif; ?>
                    <?php if ($client['city']): ?><span><i class="bi bi-geo-alt me-1 text-danger"></i><?= e(implode(', ', array_filter([$client['city'], $client['province'], $client['country']]))) ?></span><?php endif; ?>
                </div>
            </div>
            <div class="col-auto">
                <?php if (!$client['is_active']): ?><span class="badge bg-secondary mb-2">Inactive</span><?php endif; ?>
                <div class="btn-group">
                    <a href="<?= BASE_URL ?>/clients/edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <a href="<?= BASE_URL ?>/tickets/create.php?client_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>New Ticket</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="card stat-card primary"><div class="card-body"><div class="stat-value text-primary"><?= $stats['open'] ?></div><div class="stat-label">Open Tickets</div></div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card success"><div class="card-body"><div class="stat-value text-success"><?= $stats['closed'] ?></div><div class="stat-label">Resolved</div></div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card warning"><div class="card-body"><div class="stat-value text-warning" style="font-size:1.3rem"><?= money((float)$stats['unpaid']) ?></div><div class="stat-label">Outstanding</div></div></div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card success"><div class="card-body"><div class="stat-value text-success" style="font-size:1.3rem"><?= money((float)$stats['revenue']) ?></div><div class="stat-label">Total Paid</div></div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Tickets -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-ticket-detailed me-2"></i>Tickets</span>
                <a href="<?= BASE_URL ?>/tickets/create.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">New Ticket</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Ref</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($tickets)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No tickets yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/tickets/view.php?id=<?= $t['id'] ?>" class="fw-semibold text-decoration-none"><?= ticketRef($t['id']) ?></a></td>
                        <td class="text-truncate" style="max-width:180px"><?= e($t['title']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e(serviceCategoryLabel($t['category'])) ?></span></td>
                        <td><?= ticketPriorityBadge($t['priority']) ?></td>
                        <td><?= ticketStatusBadge($t['status']) ?></td>
                        <td class="small"><?= e($t['assignee_name'] ?? '—') ?></td>
                        <td class="text-muted small"><?= timeAgo($t['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($tickets) >= 20): ?>
            <div class="card-footer text-center"><a href="<?= BASE_URL ?>/tickets/index.php?client_id=<?= $id ?>">View all tickets →</a></div>
            <?php endif; ?>
        </div>

        <!-- Invoices -->
        <?php if (userCanAccessBilling()): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-receipt me-2"></i>Invoices</span>
                <a href="<?= BASE_URL ?>/invoices/create.php?client_id=<?= $id ?>" class="btn btn-sm btn-outline-success">New Invoice</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Amount</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No invoices yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="fw-semibold text-decoration-none"><?= invoiceRef($inv['id']) ?></a></td>
                        <td class="small"><?= formatDate($inv['created_at']) ?></td>
                        <td class="small"><?= formatDate($inv['due_date']) ?></td>
                        <td class="fw-semibold"><?= money($inv['total_amount']) ?></td>
                        <td><?= invoiceStatusBadge($inv['status']) ?></td>
                        <td><a href="<?= BASE_URL ?>/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-1"><i class="bi bi-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Contact Details -->
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-card-text me-2"></i>Contact Details</div>
            <div class="card-body small">
                <?php if ($client['address']): ?><p class="mb-1"><i class="bi bi-map me-1 text-muted"></i><?= e($client['address']) ?></p><?php endif; ?>
                <?php if ($client['city']): ?><p class="mb-1"><i class="bi bi-geo-alt me-1 text-muted"></i><?= e(implode(', ', array_filter([$client['city'], $client['province'], $client['postal_code']]))) ?></p><?php endif; ?>
                <?php if ($client['country']): ?><p class="mb-1"><i class="bi bi-globe me-1 text-muted"></i><?= e($client['country']) ?></p><?php endif; ?>
                <?php if ($client['notes']): ?>
                <hr class="my-2">
                <p class="mb-0 text-muted fst-italic"><?= nl2br(e($client['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Map -->
        <?php if ($client['latitude'] && $client['longitude']): ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-pin-map me-2"></i>Location</div>
            <div class="card-body p-0">
                <iframe
                    src="https://maps.google.com/maps?q=<?= e($client['latitude']) ?>,<?= e($client['longitude']) ?>&z=15&output=embed"
                    width="100%" height="200" frameborder="0" style="border-radius:0 0 10px 10px"></iframe>
            </div>
            <div class="card-footer text-center">
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= e($client['latitude']) ?>,<?= e($client['longitude']) ?>"
                   target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-navigation me-1"></i>Get Directions
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>/tickets/create.php?client_id=<?= $id ?>" class="btn btn-outline-primary btn-sm text-start"><i class="bi bi-ticket-detailed me-2"></i>Create Ticket</a>
                <?php if (userCanAccessBilling()): ?>
                <a href="<?= BASE_URL ?>/invoices/create.php?client_id=<?= $id ?>" class="btn btn-outline-success btn-sm text-start"><i class="bi bi-file-earmark-text me-2"></i>Create Invoice</a>
                <a href="<?= BASE_URL ?>/quotes/create.php?client_id=<?= $id ?>" class="btn btn-outline-info btn-sm text-start"><i class="bi bi-file-earmark-ruled me-2"></i>Create Quote</a>
                <?php endif; ?>
                <?php if ($client['email']): ?>
                <a href="mailto:<?= e($client['email']) ?>" class="btn btn-outline-secondary btn-sm text-start"><i class="bi bi-envelope me-2"></i>Send Email</a>
                <?php endif; ?>
                <?php if ($client['phone']): ?>
                <a href="tel:<?= e($client['phone']) ?>" class="btn btn-outline-secondary btn-sm text-start"><i class="bi bi-telephone me-2"></i>Call Client</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
