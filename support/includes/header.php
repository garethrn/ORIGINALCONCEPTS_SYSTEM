<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Dashboard') ?> – <?= SITE_NAME ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <?= $extraHead ?? '' ?>
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark oc-navbar">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
            <i class="bi bi-headset fs-4"></i>
            <span class="fw-bold"><?= COMPANY_NAME ?> Support</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <?php if (isLoggedIn()): ?>
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/index.php') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/tickets/') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/tickets/index.php">
                        <i class="bi bi-ticket-detailed"></i> Tickets
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/clients/') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/clients/index.php">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <?php if (hasRole('admin', 'manager')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/invoices/') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/quotes/') ? 'active' : '' ?>"
                       href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-receipt"></i> Billing
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/invoices/index.php"><i class="bi bi-file-earmark-text me-2"></i>Invoices</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotes/index.php"><i class="bi bi-file-earmark-ruled me-2"></i>Quotes</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/payments/index.php"><i class="bi bi-cash-coin me-2"></i>Payments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quickbooks/connect.php"><i class="bi bi-link-45deg me-2"></i>QuickBooks</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (hasRole('admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/users/') ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/users/index.php">
                        <i class="bi bi-person-badge"></i> Users
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-2">
                    <a class="btn btn-sm btn-warning fw-semibold" href="<?= BASE_URL ?>/tickets/create.php">
                        <i class="bi bi-plus-circle"></i> New Ticket
                    </a>
                </li>
                <?php $u = currentUser(); ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                        <span class="avatar-circle"><?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?></span>
                        <span class="d-none d-lg-inline"><?= e($u['name'] ?? '') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= e($u['name'] ?? '') ?></h6></li>
                        <li><span class="dropdown-item-text text-muted small"><?= e($u['email'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/users/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Page Content -->
<div class="container-fluid py-4 px-4">
    <?= flashHtml() ?>
