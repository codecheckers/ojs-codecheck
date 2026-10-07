<?php

/**
 * @file tests/ApiUnitTests/CurlApiClientUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CurlApiClientUnitTest
 *
 * @brief Fetching a `codecheck.yml` and resolving a DOI through Laravel's
 *   HTTP client (#65): what reaches the caller, with the network answered by
 *   address through `Factory::fake()` (#191).
 */

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

// As in production, where the classes that use it load the plugin's own
// Composer autoloader first: Guzzle classes resolved from OJS's older copy
// before it would be mixed with the plugin's for every later test.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use APP\plugins\generic\codecheck\tests\Support\Network;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use PKP\tests\PKPTestCase;

class CurlApiClientUnitTest extends PKPTestCase
{
    private Factory $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = Network::factory();
    }

    private function client(array $answers): CurlApiClient
    {
        $this->http->fake($answers);

        return new CurlApiClient($this->http);
    }

    public function testFetchAnswersTheBody()
    {
        $client = $this->client(['example.org/codecheck.yml' => Factory::response('version: x')]);

        $this->assertSame('version: x', $client->fetch('https://example.org/codecheck.yml'));
        $this->http->assertSent(fn (ClientRequest $request) => $request->url() === 'https://example.org/codecheck.yml'
            && $request->method() === 'GET');
    }

    public function testFetchFollowsRedirects()
    {
        $client = $this->client([
            // Whole addresses: a pattern without a scheme also matches the CDN's host.
            'https://example.org/codecheck.yml' => Factory::response('', 302, ['Location' => 'https://cdn.example.org/codecheck.yml']),
            'https://cdn.example.org/codecheck.yml' => Factory::response('version: x'),
        ]);

        $this->assertSame('version: x', $client->fetch('https://example.org/codecheck.yml'));
    }

    public function testAnHttpErrorKeepsItsStatus()
    {
        $client = $this->client(['example.org/*' => Factory::response('', 404)]);

        try {
            $client->fetch('https://example.org/codecheck.yml');
            $this->fail('A 404 was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testAHostThatDoesNotAnswerIs504()
    {
        $client = $this->client(['example.org/*' => $this->http->failedConnection('timed out')]);

        try {
            $client->fetch('https://example.org/codecheck.yml');
            $this->fail('A timeout was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(504, $e->getCode());
        }
    }

    public function testAnyOtherFailureIs502()
    {
        $client = $this->client([
            'example.org/*' => fn () => throw new TooManyRedirectsException('loop', new Request('GET', 'https://example.org/')),
        ]);

        try {
            $client->fetch('https://example.org/codecheck.yml');
            $this->fail('A redirect loop was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(502, $e->getCode());
        }
    }

    public function testADoiResolvesToWhereItLeads()
    {
        $client = $this->client([
            'doi.org/10.5281/zenodo.3750741' => Factory::response('', 302, ['Location' => 'https://zenodo.org/records/3750741']),
            'zenodo.org/records/3750741' => Factory::response('<html></html>'),
        ]);

        $this->assertSame('https://zenodo.org/records/3750741', $client->resolveDoi('10.5281/zenodo.3750741'));
    }

    /** A DOI written as `doi:…` resolves too, as the wizard accepts it (#190). */
    public function testAPrefixedDoiResolves()
    {
        $client = $this->client([
            'doi.org/10.5281/zenodo.3750742' => Factory::response('', 302, ['Location' => 'https://zenodo.org/records/3750742']),
            'zenodo.org/records/3750742' => Factory::response('<html></html>'),
        ]);

        $this->assertSame('https://zenodo.org/records/3750742', $client->resolveDoi('doi:10.5281/zenodo.3750742'));
    }

    /** Nothing is requested for an address that is not a DOI. */
    public function testAnAddressThatIsNotADoiIsKept()
    {
        $client = $this->client([]);

        $this->assertSame('https://github.com/a/b', $client->resolveDoi('https://github.com/a/b'));
        $this->http->assertNothingSent();
    }

    public function testADoiThatCannotBeResolvedIsKept()
    {
        $client = $this->client(['doi.org/*' => $this->http->failedConnection('timed out')]);

        $this->assertSame('10.5281/zenodo.1', $client->resolveDoi('10.5281/zenodo.1'));
    }

    /** A publish asks for the same DOI several times; it is resolved once. */
    public function testAResolvedDoiIsAskedForOnce()
    {
        $client = $this->client([
            'doi.org/10.5281/zenodo.3750743' => Factory::response('', 302, ['Location' => 'https://zenodo.org/records/3750743']),
            'zenodo.org/records/3750743' => Factory::response('<html></html>'),
        ]);

        $client->resolveDoi('10.5281/zenodo.3750743');
        $client->resolveDoi('10.5281/zenodo.3750743');

        $this->http->assertSentCount(2);
    }

    /** A DOI that leads through a plain-http address is not followed there. */
    public function testADoiIsNotFollowedToPlainHttp()
    {
        $client = $this->client([
            'doi.org/10.5281/zenodo.3750744' => Factory::response('', 302, ['Location' => 'http://internal.example/records/1']),
        ]);

        $this->assertSame('10.5281/zenodo.3750744', $client->resolveDoi('10.5281/zenodo.3750744'));
        $this->http->assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'internal.example'));
    }

    /** A GET is not sent as if it carried JSON, whatever Laravel defaults to. */
    public function testARequestCarriesNoContentType()
    {
        $client = $this->client(['example.org/*' => Factory::response('version: x')]);

        $client->fetch('https://example.org/codecheck.yml');

        $this->http->assertSent(fn (ClientRequest $request) => !$request->hasHeader('Content-Type'));
    }
}
