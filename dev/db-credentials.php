<?php

/**
 * Write the local development credentials from .env into plugin_settings.
 *
 * Secrets live in plugin_settings and deliberately never in the test dataset,
 * so dropping the database loses them. `.env` is the durable copy, and this
 * puts it back — which is what makes `make db-reset` safe to run at any time.
 *
 * Two things are deliberate here:
 *
 *  - **phpdotenv reads the file**, not a second parser written in sed. The
 *    plugin already parses .env at file scope in
 *    `CodecheckGithubRegisterApiClient`, and one file with two parsers means
 *    one value with two readings. It also means a malformed .env fails here,
 *    with a message, instead of later as a fatal on a register request.
 *  - **Prepared statements write it**, not string interpolation. Whether a
 *    backslash in a secret survives a hand-escaped literal depends on the
 *    server's sql_mode (NO_BACKSLASH_ESCAPES), which is not something a
 *    password should depend on.
 *
 * Usage (see the db-credentials target in the Makefile):
 *
 *   php dev/db-credentials.php apply   DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID
 *   php dev/db-credentials.php clear   DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID
 *
 * Nothing here prints a secret.
 */

require_once __DIR__ . '/../vendor/autoload.php';

[$script, $action, $name, $user, $pass, $host, $port, $contextId] = array_pad($argv, 8, null);

if (!in_array($action, ['apply', 'clear'], true) || $name === null) {
    fwrite(STDERR, "usage: php dev/db-credentials.php apply|clear DB_NAME DB_USER DB_PASS DB_HOST DB_PORT CONTEXT_ID\n");
    exit(2);
}

$contextId = (int) $contextId;

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
    $set('orcidEnabled', '');
    echo "Cleared the ORCID credentials from {$name}.\n";
    exit(0);
}

$clientId = trim((string) ($env['ORCID_CLIENT_ID'] ?? ''));
$secret = (string) ($env['ORCID_CLIENT_SECRET'] ?? '');

if ($clientId === '' || $secret === '') {
    echo ".env: ORCID_CLIENT_ID/ORCID_CLIENT_SECRET are blank, leaving ORCID off.\n";
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
$set('orcidEnabled', '1');

echo "Applied ORCID credentials from .env (api: {$apiType}).\n";
