<?php
/**
 * Bootstrap file for Codecheck plugin tests
 */

// Define PKP constants
if (!defined('PKP_STRICT_MODE')) {
    define('PKP_STRICT_MODE', false);
}

/**
 * Locate the OJS root.
 *
 * By default the plugin lives at <ojs>/plugins/generic/codecheck, so the root is
 * four levels up from tests/ — this is the layout CI uses.
 *
 * When the plugin is developed in a standalone checkout and linked into an OJS
 * install (see the Makefile), __FILE__ resolves through the symlink to the real
 * path and that assumption breaks. Set OJS_ROOT to point at the OJS install in
 * that case.
 */
$ojsRoot = getenv('OJS_ROOT') ?: dirname(__FILE__) . '/../../../..';

$autoloader = $ojsRoot . '/lib/pkp/lib/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, sprintf(
        "Could not find the OJS autoloader at %s\n\n" .
        "These tests need an OJS installation. Either run them from inside one\n" .
        "(<ojs>/plugins/generic/codecheck/tests), or point OJS_ROOT at an OJS\n" .
        "install:\n\n    OJS_ROOT=/path/to/ojs sh runTests.sh\n\n" .
        "See the \"Local development environment\" section of README.md.\n",
        $autoloader
    ));
    exit(1);
}

define('BASE_SYS_DIR', $ojsRoot);

// Load Composer autoloader
require_once $autoloader;

// Load our PKPTestCase stub
require_once __DIR__ . '/PKPTestCase.php';

// Make __() usable without booting the application. See FakeTranslator.
require_once __DIR__ . '/FakeTranslator.php';

if (!\Illuminate\Container\Container::getInstance()->bound('translator')) {
    \Illuminate\Container\Container::getInstance()->singleton(
        'translator',
        fn () => new \APP\plugins\generic\codecheck\tests\FakeTranslator()
    );
}

// Set include path
set_include_path(
    BASE_SYS_DIR . PATH_SEPARATOR .
    BASE_SYS_DIR . '/lib/pkp' . PATH_SEPARATOR .
    BASE_SYS_DIR . '/lib/pkp/classes' . PATH_SEPARATOR .
    get_include_path()
);
