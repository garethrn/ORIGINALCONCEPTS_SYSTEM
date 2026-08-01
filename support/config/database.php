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
            // Log error without exposing credentials
            error_log('Database connection failed: ' . $e->getMessage());
            die('<h2>Database connection failed. Please check your configuration.</h2>');
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
