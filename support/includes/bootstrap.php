<?php
/**
 * Original Concepts Support System
 * Bootstrap / Entry Point (loaded by every page)
 */

define('OC_SYSTEM', true);

// Resolve the support root regardless of which sub-folder the page lives in
define('ROOT_PATH', dirname(__DIR__));

// Compute BASE_URL as the URL path to the support root directory.
// We find the portion of SCRIPT_NAME that corresponds to the path of
// the currently running script relative to ROOT_PATH, then strip it.
// This gives the correct base regardless of which sub-folder the page lives in.
(function () {
    $scheme     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptFile = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    $rootReal   = str_replace('\\', '/', realpath(ROOT_PATH));
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // relScript: e.g. "/auth/login.php" or "/index.php"
    $relScript  = substr($scriptFile, strlen($rootReal));
    // basePath: everything in SCRIPT_NAME before relScript, e.g. "/support"
    $basePath   = substr($scriptName, 0, strlen($scriptName) - strlen($relScript));

    define('BASE_URL', rtrim($scheme . '://' . $host . $basePath, '/'));
})();

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

startSession();
