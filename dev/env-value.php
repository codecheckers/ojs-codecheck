<?php

/**
 * Print one value from the plugin's .env, using the same parser the plugin does.
 *
 * `make db-credentials` needs to read .env, and so does phpdotenv, which
 * `CodecheckGithubRegisterApiClient` loads at file scope. A second parser
 * written in sed would read quoting and escapes differently from the first, so
 * one file would mean two values — and a line phpdotenv rejects would not show
 * up here at all, it would show up later as a fatal on any register request.
 *
 * So there is one parser, and this is the way to it.
 *
 *   php dev/env-value.php ORCID_CLIENT_SECRET
 *
 * Prints the value with no trailing newline, or nothing when the key is unset.
 * Exits non-zero, with the reason on stderr, when .env cannot be parsed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$key = $argv[1] ?? null;
if ($key === null) {
    fwrite(STDERR, "usage: php dev/env-value.php <KEY>\n");
    exit(2);
}

try {
    // safeLoad(): no .env at all is not an error, an unparseable one is.
    $values = Dotenv\Dotenv::createArrayBacked(__DIR__ . '/..')->safeLoad();
} catch (Throwable $e) {
    fwrite(STDERR, 'Cannot parse .env: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $values[$key] ?? '';
