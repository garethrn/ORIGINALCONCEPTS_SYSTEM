<?php
/**
 * Original Concepts Support System
 * Helper / Utility Functions
 */

if (!defined('OC_SYSTEM')) {
    die('Direct access not permitted.');
}

// ─── Security Helpers ─────────────────────────────────────────────────────────

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize(string $str): string
{
    return trim(strip_tags($str));
}

function generateToken(): string
{
    return bin2hex(random_bytes(32));
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

// ─── Redirect Helpers ─────────────────────────────────────────────────────────

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function redirectWithMessage(string $url, string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    redirect($url);
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flashHtml(): string
{
    $flash = getFlash();
    if (!$flash) return '';
    $type    = in_array($flash['type'], ['success','danger','warning','info']) ? $flash['type'] : 'info';
    $message = e($flash['message']);
    return "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                {$message}
                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
            </div>";
}

// ─── Date / Time Helpers ──────────────────────────────────────────────────────

function formatDate(string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '-';
}

function formatDateTime(string $date, string $format = 'd M Y H:i'): string
{
    return $date ? date($format, strtotime($date)) : '-';
}

function timeAgo(string $datetime): string
{
    $time = time() - strtotime($datetime);
    if ($time < 60)         return $time . 's ago';
    if ($time < 3600)       return floor($time / 60) . 'm ago';
    if ($time < 86400)      return floor($time / 3600) . 'h ago';
    if ($time < 2592000)    return floor($time / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

// ─── Money Helpers ────────────────────────────────────────────────────────────

function money(float $amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2, '.', ',');
}

function appSettingsTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        dbQuery('SELECT 1 FROM app_settings LIMIT 1');
        $exists = true;
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function appSetting(string $key, string $default = ''): string
{
    if (!appSettingsTableExists()) {
        return $default;
    }

    $row = dbRow('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$key]);
    if (!$row) {
        return $default;
    }

    return (string)$row['setting_value'];
}

function appSettingBool(string $key, bool $default = false): bool
{
    return appSetting($key, $default ? '1' : '0') === '1';
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array
{
    $totalPages = (int) ceil($total / $perPage);
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;

    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'page'        => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

function paginationHtml(array $pager, string $baseUrl): string
{
    if ($pager['total_pages'] <= 1) return '';

    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';

    $prev = $pager['page'] - 1;
    $next = $pager['page'] + 1;

    $html .= '<li class="page-item' . ($pager['page'] <= 1 ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . e($baseUrl . $sep . 'page=' . $prev) . '">‹ Prev</a></li>';

    for ($i = max(1, $pager['page'] - 2); $i <= min($pager['total_pages'], $pager['page'] + 2); $i++) {
        $active = $i === $pager['page'] ? ' active' : '';
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($pager['page'] >= $pager['total_pages'] ? ' disabled' : '') . '">'
           . '<a class="page-link" href="' . e($baseUrl . $sep . 'page=' . $next) . '">Next ›</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

// ─── Ticket Reference ─────────────────────────────────────────────────────────

function ticketRef(int $id): string
{
    return 'OC-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function invoiceRef(int $id): string
{
    return 'INV-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function quoteRef(int $id): string
{
    return 'QT-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

// ─── Badge Helpers ────────────────────────────────────────────────────────────

function ticketStatusBadge(string $status): string
{
    $statuses = TICKET_STATUSES;
    $s = $statuses[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    return '<span class="badge bg-' . $s['color'] . '">' . e($s['label']) . '</span>';
}

function ticketPriorityBadge(string $priority): string
{
    $priorities = TICKET_PRIORITIES;
    $p = $priorities[$priority] ?? ['label' => ucfirst($priority), 'color' => 'secondary'];
    return '<span class="badge bg-' . $p['color'] . '">' . e($p['label']) . '</span>';
}

function invoiceStatusBadge(string $status): string
{
    $statuses = INVOICE_STATUSES;
    $s = $statuses[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];
    return '<span class="badge bg-' . $s['color'] . '">' . e($s['label']) . '</span>';
}

function serviceCategoryLabel(string $cat): string
{
    $cats = SERVICE_CATEGORIES;
    return $cats[$cat] ?? ucfirst(str_replace('_', ' ', $cat));
}

// ─── Email Notification ───────────────────────────────────────────────────────

function sendMail(string $to, string $subject, string $body, string $toName = ''): bool
{
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM . "\r\n";

    $wrapped = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;">'
             . '<div style="max-width:600px;margin:0 auto;padding:20px;">'
             . '<div style="background:#1a3c5e;padding:15px;border-radius:4px 4px 0 0;">'
             . '<img src="' . SITE_URL . '/assets/img/logo.png" alt="' . COMPANY_NAME . '" style="height:40px;" onerror="this.style.display=\'none\'">'
             . '<h2 style="color:#fff;margin:0;">' . COMPANY_NAME . ' Support</h2>'
             . '</div>'
             . '<div style="border:1px solid #ddd;border-top:none;padding:20px;border-radius:0 0 4px 4px;">'
             . $body
             . '</div>'
             . '<p style="font-size:12px;color:#888;margin-top:10px;">This is an automated message from ' . COMPANY_NAME . ' Support System.</p>'
             . '</div></body></html>';

    return mail($to, $subject, $wrapped, $headers);
}

function sendTicketNotification(array $ticket, string $event): void
{
    if (empty($ticket['client_email'])) return;

    $ref = ticketRef($ticket['id']);

    switch ($event) {
        case 'created':
            $subject = "Ticket {$ref} Created – " . $ticket['title'];
            $body    = "<p>Dear {$ticket['client_name']},</p>
                        <p>Your support ticket has been created successfully.</p>
                        <table style='border-collapse:collapse;width:100%'>
                            <tr><td style='padding:6px;border:1px solid #ddd'><strong>Reference</strong></td><td style='padding:6px;border:1px solid #ddd'>{$ref}</td></tr>
                            <tr><td style='padding:6px;border:1px solid #ddd'><strong>Subject</strong></td><td style='padding:6px;border:1px solid #ddd'>" . e($ticket['title']) . "</td></tr>
                            <tr><td style='padding:6px;border:1px solid #ddd'><strong>Priority</strong></td><td style='padding:6px;border:1px solid #ddd'>" . ucfirst($ticket['priority']) . "</td></tr>
                            <tr><td style='padding:6px;border:1px solid #ddd'><strong>Status</strong></td><td style='padding:6px;border:1px solid #ddd'>Open</td></tr>
                        </table>
                        <p>We will be in touch shortly.</p>
                        <p><a href='" . SITE_URL . "/tickets/view.php?id=" . $ticket['id'] . "' style='background:#1a3c5e;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px;'>View Ticket</a></p>";
            break;

        case 'updated':
            $subject = "Ticket {$ref} Updated";
            $body    = "<p>Dear {$ticket['client_name']},</p>
                        <p>Your ticket <strong>{$ref}</strong> has been updated. Current status: <strong>" . ucfirst(str_replace('_', ' ', $ticket['status'])) . "</strong></p>
                        <p><a href='" . SITE_URL . "/tickets/view.php?id=" . $ticket['id'] . "' style='background:#1a3c5e;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px;'>View Ticket</a></p>";
            break;

        case 'resolved':
            $subject = "Ticket {$ref} Resolved";
            $body    = "<p>Dear {$ticket['client_name']},</p>
                        <p>We're pleased to inform you that your ticket <strong>{$ref}</strong> has been resolved.</p>
                        <p>If you are satisfied with the resolution, no further action is needed. If you still have issues, please reply or open a new ticket.</p>
                        <p><a href='" . SITE_URL . "/tickets/view.php?id=" . $ticket['id'] . "' style='background:#1a3c5e;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px;'>View Ticket</a></p>";
            break;

        default:
            return;
    }

    sendMail($ticket['client_email'], $subject, $body, $ticket['client_name']);
}

// ─── Input Validation ─────────────────────────────────────────────────────────

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool
{
    return preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $phone) === 1;
}

// ─── File Upload ──────────────────────────────────────────────────────────────

function handleUpload(array $file, string $subfolder = ''): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE)  return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED)) return null;

    $dir = rtrim(UPLOAD_DIR . $subfolder, '/') . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('oc_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ($subfolder ? $subfolder . '/' : '') . $filename;
    }

    return null;
}
