<?php

/**
 * A `php -S` router that tells OJS the request arrived over HTTPS.
 *
 * Needed because ORCID accepts only https:// redirect URIs, so a live ORCID
 * round trip has to run behind a TLS terminator — and `PKPRequest::getProtocol()`
 * decides http vs https from `$_SERVER['HTTPS']` alone. It does not read
 * `X-Forwarded-Proto`, and `base_url` in config.inc.php does not help either:
 * `getBaseUrl()` only falls back to it when server-host auto-detection fails,
 * which is to say on the command line. So OJS behind `socat` announces itself
 * as http, ORCID is handed an http redirect URI, and the callback is refused.
 *
 * Apache would be told this with `SetEnv HTTPS on`. The built-in server has no
 * such thing — and an `HTTPS=on` environment variable does not reach `$_SERVER`
 * — so it is set here instead, before OJS bootstraps.
 *
 * Unconditional on purpose: a server started with this router exists only to
 * sit behind the TLS front-end (`make serve-https` plus `make serve-tls`).
 * Never use it for a server that is reachable directly.
 *
 *   OJS_ROOT=/path/to/ojs php -S localhost:8350 -t /path/to/ojs dev/https-router.php
 */

$_SERVER['HTTPS'] = 'on';

$root = realpath(getenv('OJS_ROOT') ?: __DIR__ . '/../../ojs-350');

if ($root === false) {
    http_response_code(500);
    exit("https-router: OJS_ROOT does not exist\n");
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = realpath($root . $path);

// Serve an existing file: PHP is executed, anything else is handed back to the
// built-in server. str_starts_with keeps a crafted path inside the OJS tree.
if ($file !== false && is_file($file) && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SCRIPT_NAME'] = $path;
        require $file;
        return true;
    }
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
