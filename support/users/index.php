<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin');

$pageTitle = 'Users';

$users = dbRows("SELECT u.*, (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) AS ticket_count FROM users u ORDER BY u.name");

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2"></i>System Users</h4>
    <a href="<?= BASE_URL ?>/users/create.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Add User
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Assigned Tickets</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle" style="width:28px;height:28px;font-size:.75rem"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                            <span><?= e($u['name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : ($u['role'] === 'manager' ? 'primary' : ($u['role'] === 'technician' ? 'info' : 'light text-dark border')) ?>">
                            <?= e(USER_ROLES[$u['role']] ?? ucfirst($u['role'])) ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $u['ticket_count'] ?></td>
                    <td class="text-muted small"><?= $u['last_login_at'] ? timeAgo($u['last_login_at']) : 'Never' ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
