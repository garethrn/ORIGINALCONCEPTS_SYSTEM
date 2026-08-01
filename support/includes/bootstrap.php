<?php
/**
 * Original Concepts Support System
 * Bootstrap / Entry Point (loaded by every page)
 */

define('OC_SYSTEM', true);

// Resolve the support root regardless of which sub-folder the page lives in
define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL',  rtrim(
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . preg_replace('#/[^/]*$#', '', $_SERVER['SCRIPT_NAME'] ?? ''),
    '/'
));

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

startSession();
