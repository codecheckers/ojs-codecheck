<?php

/**
 * @file classes/CodecheckRegister/GithubHttp.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class GithubHttp
 *
 * @brief The one way the plugin builds a GitHub API client: with a time limit,
 *   and with a request that stops asking GitHub once it has failed to answer.
 *
 * The client knplabs builds by default has no time limit at all, so a GitHub
 * that accepts the connection and never answers held an editor's save, a
 * status change or a publication for as long as PHP let the request run. Every
 * write to the register is reached from one of those, and since #186 a save
 * makes several calls in turn.
 *
 * The time limit bounds one call; the breaker bounds the request. Once a call
 * has gone unanswered — a connection refused or a time limit reached — every
 * later call in the same PHP request fails at once rather than waiting out its
 * own limit, so a save that would have made five calls waits for one. It is a
 * static and therefore lasts one request under mod_php or FPM; nothing is
 * remembered across requests, because the next one should simply try again.
 * An answer GitHub did give — a 404, a 422, a refusal — is not a failure to
 * answer and trips nothing.
 */

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

require_once __DIR__ . '/../../vendor/autoload.php';

use Github\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use PKP\config\Config;
use Psr\Http\Message\RequestInterface;

class GithubHttp
{
    /** How long one call may take, end to end. */
    public const TIMEOUT_SECONDS = 10;

    /** How long establishing the connection may take, within that. */
    public const CONNECT_TIMEOUT_SECONDS = 5;

    private static bool $unreachable = false;

    /**
     * A GitHub API client for the register, with the time limits and the
     * breaker. `$handler` replaces the transport, for tests.
     */
    public static function client(?callable $handler = null): Client
    {
        return Client::createWithHttpClient(new GuzzleClient(self::options($handler)));
    }

    /**
     * The options the HTTP client is built with.
     *
     * @return array<string, mixed>
     */
    public static function options(?callable $handler = null): array
    {
        $stack = HandlerStack::create($handler);
        $stack->push(self::breaker(), 'codecheck_github_breaker');

        return [
            'handler' => $stack,
            'timeout' => self::TIMEOUT_SECONDS,
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'proxy' => self::proxy(),
        ];
    }

    /**
     * The journal's proxy, as OJS's own client takes it
     * (`PKPApplication::getHttpClient()`): a journal behind one reaches the
     * register as it reaches everything else. None where OJS's configuration
     * cannot be read — under PHPUnit, which does not boot OJS — since there is
     * then no configuration to honour.
     *
     * @return array{http: ?string, https: ?string}
     */
    private static function proxy(): array
    {
        try {
            return [
                'http' => Config::getVar('proxy', 'http_proxy', null),
                'https' => Config::getVar('proxy', 'https_proxy', null),
            ];
        } catch (\Throwable) {
            return ['http' => null, 'https' => null];
        }
    }

    /**
     * Whether GitHub failed to answer earlier in this request: what the
     * editor is told, rather than the transport's own message.
     */
    public static function wasUnreachable(): bool
    {
        return self::$unreachable;
    }

    /**
     * What the editor is told when GitHub did not answer, with the time it was
     * given: the one wording for the register writes and the syncs alike.
     */
    public static function unreachableMessage(string $key = 'plugins.generic.codecheck.register.unreachable'): string
    {
        return __($key, ['seconds' => self::TIMEOUT_SECONDS]);
    }

    /** Forget a failure, for tests: nothing else resets it within a request. */
    public static function reset(): void
    {
        self::$unreachable = false;
    }

    /**
     * Guzzle middleware: refuses at once after a failure to answer, and notes
     * one when it happens. Guzzle reports a time limit reached as a
     * ConnectException, as it does a connection refused.
     */
    private static function breaker(): callable
    {
        return static fn (callable $next) => static function (RequestInterface $request, array $options) use ($next) {
            if (self::$unreachable) {
                return Create::rejectionFor(new ConnectException(
                    'GitHub did not answer earlier in this request, so it was not asked again.',
                    $request
                ));
            }

            return $next($request, $options)->then(null, static function ($reason) {
                if ($reason instanceof ConnectException) {
                    self::$unreachable = true;
                }

                return Create::rejectionFor($reason);
            });
        };
    }
}
