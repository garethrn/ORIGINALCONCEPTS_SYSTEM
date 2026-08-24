<?php
/**
 * Original Concepts Support System
 * Global Configuration
 */

// Prevent direct access to config files
if (!defined('OC_SYSTEM')) {
    die('Direct access not permitted.');
}

// ─── Site Settings ────────────────────────────────────────────────────────────
define('SITE_NAME',    'Original Concepts Support');
define('SITE_URL',     'https://www.originalconcepts.co.za/support');
define('SITE_EMAIL',   'support@originalconcepts.co.za');
define('COMPANY_NAME', 'Original Concepts');
define('COMPANY_PHONE', '+27 (0) 000 000 0000');
define('COMPANY_ADDRESS', 'South Africa');

// ─── Timezone ─────────────────────────────────────────────────────────────────
define('SITE_TIMEZONE', 'Africa/Johannesburg');
date_default_timezone_set(SITE_TIMEZONE);

// ─── Session ──────────────────────────────────────────────────────────────────
define('SESSION_NAME',     'oc_support_session');
define('SESSION_LIFETIME', 28800); // 8 hours

// ─── Pagination ───────────────────────────────────────────────────────────────
define('ITEMS_PER_PAGE', 20);

// ─── Email / SMTP ─────────────────────────────────────────────────────────────
define('SMTP_HOST',     'mail.originalconcepts.co.za');
define('SMTP_PORT',     587);
define('SMTP_USER',     'support@originalconcepts.co.za');
define('SMTP_PASS',     '');           // Set via .env or manually
define('SMTP_SECURE',   'tls');        // tls | ssl
define('SMTP_FROM',     'support@originalconcepts.co.za');
define('SMTP_FROM_NAME', 'Original Concepts Support');

// ─── QuickBooks ───────────────────────────────────────────────────────────────
define('QB_CLIENT_ID',     '');        // From QuickBooks Developer Console
define('QB_CLIENT_SECRET', '');        // From QuickBooks Developer Console
define('QB_REDIRECT_URI',  rtrim(SITE_URL, '/') . '/quickbooks/callback.php');
define('QB_SCOPE',         'com.intuit.quickbooks.accounting');
define('QB_ENVIRONMENT',   'production'); // 'sandbox' | 'production'
define('QB_BASE_URL',      'https://quickbooks.api.intuit.com');
define('QB_AUTH_URL',      'https://appcenter.intuit.com/connect/oauth2');
define('QB_TOKEN_URL',     'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
define('QB_DISCOVERY_URL', 'https://developer.api.intuit.com/.well-known/openid_sandbox_configuration');
define('QB_MINOR_VERSION', '65');

// ─── Upload Settings ──────────────────────────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('UPLOAD_ALLOWED',  ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','gif','zip','txt']);

// ─── Currency ─────────────────────────────────────────────────────────────────
define('CURRENCY',        'ZAR');
define('CURRENCY_SYMBOL', 'R');

// ─── Service Categories ───────────────────────────────────────────────────────
define('SERVICE_CATEGORIES', [
    'it_support'    => 'IT Support',
    'web_dev'       => 'Web Development',
    'whatsapp_dev'  => 'WhatsApp Development',
    'software_dev'  => 'Software Development',
    'networking'    => 'Networking',
    'cloud'         => 'Cloud Services',
    'cybersecurity' => 'Cybersecurity',
    'consulting'    => 'Consulting',
    'other'         => 'Other',
]);

// ─── Ticket Priorities ────────────────────────────────────────────────────────
define('TICKET_PRIORITIES', [
    'low'      => ['label' => 'Low',      'color' => 'success'],
    'medium'   => ['label' => 'Medium',   'color' => 'warning'],
    'high'     => ['label' => 'High',     'color' => 'danger'],
    'critical' => ['label' => 'Critical', 'color' => 'dark'],
]);

// ─── Ticket Statuses ──────────────────────────────────────────────────────────
define('TICKET_STATUSES', [
    'open'        => ['label' => 'Open',        'color' => 'primary'],
    'in_progress' => ['label' => 'In Progress',  'color' => 'info'],
    'waiting'     => ['label' => 'Waiting',      'color' => 'warning'],
    'resolved'    => ['label' => 'Resolved',     'color' => 'success'],
    'closed'      => ['label' => 'Closed',       'color' => 'secondary'],
    'cancelled'   => ['label' => 'Cancelled',    'color' => 'danger'],
]);

// ─── Invoice Statuses ─────────────────────────────────────────────────────────
define('INVOICE_STATUSES', [
    'draft'    => ['label' => 'Draft',    'color' => 'secondary'],
    'sent'     => ['label' => 'Sent',     'color' => 'info'],
    'paid'     => ['label' => 'Paid',     'color' => 'success'],
    'overdue'  => ['label' => 'Overdue',  'color' => 'danger'],
    'void'     => ['label' => 'Void',     'color' => 'dark'],
]);

// ─── User Roles ───────────────────────────────────────────────────────────────
define('USER_ROLES', [
    'admin'      => 'Administrator',
    'manager'    => 'Manager',
    'technician' => 'Technician',
    'client'     => 'Client',
]);
