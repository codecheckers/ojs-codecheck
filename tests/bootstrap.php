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
$classLoader = require_once $autoloader;

/**
 * Resolve the plugin's own classes to *this* checkout.
 *
 * OJS's autoloader maps `APP\plugins\generic\codecheck\…` through
 * `<ojs>/plugins/generic/codecheck`, which is a symlink, and a single shared
 * one: while it points at a git worktree, the tests in this directory run
 * against that worktree's classes instead of the ones beside them. The failure
 * is not an error but a wrong answer — a test written here fails against code
 * it was never written for, and passes against code nobody is looking at.
 *
 * The tests in a checkout test that checkout, so its own directory is
 * prepended. Nothing else changes: OJS's classes still come from `lib/pkp`.
 */
if (!$classLoader instanceof \Composer\Autoload\ClassLoader) {
    // `require_once` answers true rather than the loader when something has
    // already required it — which the PHPUnit in `lib/pkp` has, since that is
    // how it was itself loaded. The registered autoloaders are then the only
    // place the instance can be had.
    foreach (spl_autoload_functions() ?: [] as $autoload) {
        if (is_array($autoload) && ($autoload[0] ?? null) instanceof \Composer\Autoload\ClassLoader) {
            $classLoader = $autoload[0];
            break;
        }
    }
}

if ($classLoader instanceof \Composer\Autoload\ClassLoader) {
    // A longer PSR-4 prefix than OJS's `APP\plugins\`, so it is tried first.
    $classLoader->addPsr4('APP\\plugins\\generic\\codecheck\\', dirname(__DIR__), true);
} else {
    fwrite(
        STDERR,
        "Warning: could not reach Composer's class loader, so the plugin's own\n" .
        "classes are resolved through <ojs>/plugins/generic/codecheck. If that\n" .
        "symlink points elsewhere, these tests are running against other code.\n"
    );
}

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
