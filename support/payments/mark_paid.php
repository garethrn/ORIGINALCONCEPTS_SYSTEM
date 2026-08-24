<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireBillingAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/payments/index.php');
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$invoice   = dbRow('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);

if (!$invoice || in_array($invoice['status'], ['paid', 'void'])) {
    redirectWithMessage(BASE_URL . '/payments/index.php', 'Invalid invoice or already paid.', 'warning');
}

dbUpdate('invoices', [
    'status'     => 'paid',
    'paid_at'    => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
], ['id' => $invoiceId]);

redirectWithMessage(BASE_URL . '/invoices/view.php?id=' . $invoiceId, invoiceRef($invoiceId) . ' marked as paid successfully.');
