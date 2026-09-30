<?php

/**
 * Write the local development credentials from .env into plugin_settings.
 *
 * Secrets live in plugin_settings and deliberately never in the test dataset,
 * so dropping the database loses them. `.env` is the durable copy, and this
 * puts it back — which is what makes `make db-reset` safe to run at any time.
 *
 * **It applies the credentials and leaves ORCID switched off**, so a reset
 * always lands in the state a test run needs: see the comment where
 * `orcidEnabled` is written.
 *
 * Three things are deliberate here:
 *
 *  - **phpdotenv reads the file**, not a second parser written in sed: a
 *    quoted value, an escape or a `#` inside a password is then read the way
 *    the format says, and a malformed .env fails here with a message rather
 *    than halfway through writing.
 *  - **Prepared statements write it**, not string interpolation. Whether a
 *    backslash in a secret survives a hand-escaped literal depends on the
 *    server's sql_mode (NO_BACKSLASH_ESCAPES), which is not something a
 *    password should depend on.
 *
 * **phpdotenv comes from the OJS installation, not from the plugin's
 * `vendor/`.** It used to be a dependency of the plugin, and #50 removed it —
 * nothing at runtime reads an environment variable any more — which left this
 * script requiring a class that is no longer installed, so `make db-load` and
 * `make db-reset` died at this step for anyone with a `.env`. Adding it back to
 * the plugin's `require` would put a development-only package into the
 * `vendor/` that Composer registers *prepended* for every request touching the
 * plugin, which is the thing the coding-standard note in CLAUDE.md is about. So
 * this reaches for OJS's copy: `make db-credentials` already depends on
 * `check-ojs`, so an installation is there, and PKP ships phpdotenv itself.
 *
 * Usage (see the db-credentials target in the Makefile):
 *
 *   php dev/db-credentials.php apply   DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID [OJS_ROOT]
 *   php dev/db-credentials.php clear   DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID [OJS_ROOT]
 *
 * OJS_ROOT may also come from the environment; it defaults to the four-levels-up
 * layout a plugin installed inside OJS has, as tests/bootstrap.php does.
 *
 * Nothing here prints a secret.
 */

[$script, $action, $name, $user, $pass, $host, $port, $contextId, $ojsRoot] = array_pad($argv, 9, null);

if (!in_array($action, ['apply', 'clear'], true) || $name === null) {
    fwrite(STDERR, "usage: php dev/db-credentials.php apply|clear DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID [OJS_ROOT]\n");
    exit(2);
}

$contextId = (int) $contextId;

$ojsRoot = $ojsRoot ?: (getenv('OJS_ROOT') ?: __DIR__ . '/../../../..');
$autoloader = $ojsRoot . '/lib/pkp/lib/vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, sprintf(
        "Could not find the OJS autoloader at %s\n" .
        "This script reads .env with the phpdotenv that OJS ships. Pass the OJS\n" .
        "root as the last argument, or set OJS_ROOT.\n",
        $autoloader
    ));
    exit(1);
}

require_once $autoloader;

if (!class_exists(Dotenv\Dotenv::class)) {
    fwrite(STDERR, sprintf(
        "The OJS installation at %s has no vlucas/phpdotenv, which this script\n" .
        "reads .env with. Run `composer install` inside its lib/pkp.\n",
        $ojsRoot
    ));
    exit(1);
}

/**
 * How the settings form spells the ORCID switch.
 *
 * It is a checkbox, so a ticked one saves as `on` and an unticked one as the
 * empty string. This script writes the same two values rather than `1`, so a
 * setting written here and one written through the form cannot be told apart.
 */
const ORCID_ENABLED_ON = 'on';
const ORCID_ENABLED_OFF = '';

/** The plugin settings this script owns, and where each one comes from. */
const ORCID_SETTINGS = [
    'orcidClientId' => 'ORCID_CLIENT_ID',
    'orcidClientSecret' => 'ORCID_CLIENT_SECRET',
    'orcidApiType' => 'ORCID_API_TYPE',
    'orcidCity' => 'ORCID_CITY',
];

try {
    // safeLoad(): no .env at all is fine, an unparseable one is not.
    $env = Dotenv\Dotenv::createArrayBacked(__DIR__ . '/..')->safeLoad();
} catch (Throwable $e) {
    fwrite(STDERR, 'Cannot parse .env: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Cannot connect to the database: ' . $e->getMessage() . "\n");
    exit(1);
}

$write = $pdo->prepare(
    'INSERT INTO plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type)
     VALUES (:plugin, :context, :name, :value, :type)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);

$set = function (string $setting, string $value) use ($write, $contextId): void {
    $write->execute([
        ':plugin' => 'codecheckplugin',
        ':context' => $contextId,
        ':name' => $setting,
        ':value' => $value,
        ':type' => 'string',
    ]);
};

if ($action === 'clear') {
    foreach (array_keys(ORCID_SETTINGS) as $setting) {
        $set($setting, '');
    }
    $set('orcidEnabled', ORCID_ENABLED_OFF);
    echo "Cleared the ORCID credentials from {$name}.\n";
    exit(0);
}

$clientId = trim((string) ($env['ORCID_CLIENT_ID'] ?? ''));
$secret = (string) ($env['ORCID_CLIENT_SECRET'] ?? '');

if ($clientId === '' || $secret === '') {
    echo ".env: ORCID_CLIENT_ID/ORCID_CLIENT_SECRET are blank, nothing to apply.\n";
    // Still switched off explicitly, so a reset lands in the same state either
    // way rather than leaving whatever was there.
    $set('orcidEnabled', ORCID_ENABLED_OFF);
    exit(0);
}

$apiType = trim((string) ($env['ORCID_API_TYPE'] ?? '')) ?: 'memberSandbox';

if (!in_array($apiType, ['memberSandbox', 'member'], true)) {
    fwrite(STDERR, "ORCID_API_TYPE must be 'memberSandbox' or 'member', not '{$apiType}'.\n");
    exit(1);
}

foreach (ORCID_SETTINGS as $setting => $envKey) {
    $value = (string) ($env[$envKey] ?? '');
    $set($setting, $setting === 'orcidApiType' ? $apiType : $value);
}

// **The credentials go in; the switch stays off.** Having them on file is what
// saves re-typing a secret after a reset, but ORCID being *enabled* makes
// publishing deposit to ORCID — `Publication::publish` reaches for it, and asks
// ORCID to register the journal's group id before it checks whether there is
// anything to deposit. So a rebuilt database would make the e2e suite call the
// ORCID sandbox, which no test run should do. Turn it on in the plugin settings
// when that is what you actually want, as dev/live-orcid-tests.md says.
$set('orcidEnabled', ORCID_ENABLED_OFF);

echo "Applied ORCID credentials from .env (api: {$apiType}); ORCID is left switched off.\n";
