<?php
/**
 * Bootstrap file for Codecheck plugin tests
 */

// Define PKP constants
if (!defined('PKP_STRICT_MODE')) {
    define('PKP_STRICT_MODE', false);
}

// Get the OJS root directory (4 levels up from tests/)
define('BASE_SYS_DIR', dirname(__FILE__) . '/../../../..');

// Load Composer autoloader
require_once BASE_SYS_DIR . '/lib/pkp/lib/vendor/autoload.php';

// Load our PKPTestCase stub - THIS LINE WAS MISSING!
require_once __DIR__ . '/PKPTestCase.php';

// Set include path
set_include_path(
    BASE_SYS_DIR . PATH_SEPARATOR .
    BASE_SYS_DIR . '/lib/pkp' . PATH_SEPARATOR .
    BASE_SYS_DIR . '/lib/pkp/classes' . PATH_SEPARATOR .
    get_include_path()
);