<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

logout();
redirect(BASE_URL . '/auth/login.php');
