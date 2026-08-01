<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    dbQuery('DELETE FROM qb_tokens');
    redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'QuickBooks disconnected.');
}

redirect(BASE_URL . '/quickbooks/connect.php');
