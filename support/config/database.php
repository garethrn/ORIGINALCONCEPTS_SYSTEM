<?php
/**
 * Original Concepts Support System
 * Database Configuration & Connection
 */

if (!defined('OC_SYSTEM')) {
    die('Direct access not permitted.');
}

// ─── Database Credentials ─────────────────────────────────────────────────────
// Update these values with your hosting provider's database details.
define('DB_HOST',    'localhost');
define('DB_NAME',    'oc_support');
define('DB_USER',    'oc_user');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            // Output a standalone error page so the message is always visible,
            // even if called mid-way through rendering another page's HTML.
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(503);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
               . '<meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>Database Error</title>'
               . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
               . '</head><body class="bg-light">'
               . '<div class="container py-5"><div class="alert alert-danger shadow-sm">'
               . '<h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Database Connection Failed</h4>'
               . '<p class="mb-2">The system could not connect to the database. Please check your database credentials in <code>config/database.php</code>.</p>'
               . '<hr><p class="mb-0 small text-muted">If you have not run the installer yet, visit <a href="/support/install/">/support/install/</a> to set up the database.</p>'
               . '</div></div>'
               . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">'
               . '</body></html>';
            exit;
        }
    }

    return $pdo;
}

/**
 * Execute a parameterised query and return the statement.
 */
function dbQuery(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch a single row.
 */
function dbRow(string $sql, array $params = []): ?array
{
    $row = dbQuery($sql, $params)->fetch();
    return $row ?: null;
}

/**
 * Fetch all rows.
 */
function dbRows(string $sql, array $params = []): array
{
    return dbQuery($sql, $params)->fetchAll();
}

/**
 * Insert a row and return the last insert ID.
 */
function dbInsert(string $table, array $data): int
{
    $cols   = implode(', ', array_keys($data));
    $places = implode(', ', array_fill(0, count($data), '?'));
    $sql    = "INSERT INTO `{$table}` ({$cols}) VALUES ({$places})";
    dbQuery($sql, array_values($data));
    return (int) db()->lastInsertId();
}

/**
 * Update rows matching $where conditions.
 */
function dbUpdate(string $table, array $data, array $where): int
{
    $set   = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
    $cond  = implode(' AND ', array_map(fn($c) => "`{$c}` = ?", array_keys($where)));
    $sql   = "UPDATE `{$table}` SET {$set} WHERE {$cond}";
    $stmt  = dbQuery($sql, array_merge(array_values($data), array_values($where)));
    return $stmt->rowCount();
}
