<?php

/**
 * @file tests/Support/GithubFake.php
 *
 * @class GithubFake
 *
 * @brief GitHub's API for tests, answering by address (#191): a route is a
 *   method and a path, so a test says which requests it expects and what
 *   GitHub answers, not which PHP methods the code calls in which order.
 */

namespace APP\plugins\generic\codecheck\tests\Support;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use Github\Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

class GithubFake
{
    /** @var array<string, mixed> route pattern → answer */
    private array $routes;

    /** @var list<RequestInterface> */
    private array $requests = [];

    /**
     * Routes are `'GET /repos/o/r/issues/*'`: the method, a space and the path,
     * where `*` matches anything. The first matching route answers. An answer is
     * a PSR response, an array (answered as JSON, 200), a Throwable (the
     * transport failing) or a Closure taking the request and answering one of those.
     *
     * @param array<string, mixed> $routes
     */
    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    /** Adds a route after those already there, for a test that builds up its answers. */
    public function route(string $pattern, mixed $answer): self
    {
        $this->routes[$pattern] = $answer;

        return $this;
    }

    /** The GitHub client the plugin builds, over this fake and with its time limits and breaker. */
    public function client(): Client
    {
        return GithubHttp::client($this);
    }

    /** Guzzle handler: what the client calls in place of the network. */
    public function __invoke(RequestInterface $request, array $options)
    {
        $this->requests[] = $request;
        $address = self::addressOf($request);

        foreach ($this->routes as $pattern => $answer) {
            if (!self::matches($pattern, $address)) {
                continue;
            }
            if ($answer instanceof \Closure) {
                $answer = $answer($request);
            }
            if ($answer instanceof \Throwable) {
                return Create::rejectionFor($answer);
            }
            if (is_array($answer)) {
                $answer = new Response(200, ['Content-Type' => 'application/json'], json_encode($answer));
            }

            // The same answer may serve several requests, so its body starts over each time.
            $answer->getBody()->rewind();

            return Create::promiseFor($answer);
        }

        throw new \LogicException("Unexpected request to GitHub: {$address}");
    }

    /** @return list<RequestInterface> in the order they were made */
    public function requests(string $pattern = '*'): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (RequestInterface $request) => self::matches($pattern, self::addressOf($request))
        ));
    }

    /** The JSON bodies sent to the addresses matching the pattern, in order. */
    public function bodies(string $pattern): array
    {
        return array_map(fn (RequestInterface $request) => json_decode((string) $request->getBody(), true), $this->requests($pattern));
    }

    /** Asserts how many requests matched the pattern, and that `$check($request, $body)` holds for each. */
    public function assertSent(string $pattern, ?callable $check = null, int $times = 1): void
    {
        $matching = $this->requests($pattern);
        Assert::assertCount($times, $matching, "Requests to {$pattern}");
        if ($check) {
            foreach ($matching as $request) {
                Assert::assertNotFalse($check($request, json_decode((string) $request->getBody(), true)), "Check of a request to {$pattern}");
            }
        }
    }

    public function assertNotSent(string $pattern): void
    {
        Assert::assertSame([], $this->requests($pattern), "Requests to {$pattern} were not expected");
    }

    /** The addresses asked, in order, as `METHOD /path`. */
    public function addresses(): array
    {
        return array_map(fn (RequestInterface $request) => self::addressOf($request), $this->requests);
    }

    private static function addressOf(RequestInterface $request): string
    {
        return $request->getMethod() . ' ' . $request->getUri()->getPath();
    }

    private static function matches(string $pattern, string $address): bool
    {
        return preg_match('#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#', $address) === 1;
    }
}
