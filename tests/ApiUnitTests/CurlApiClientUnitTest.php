<?php

/**
 * @file tests/ApiUnitTests/CurlApiClientUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CurlApiClientUnitTest
 *
 * @brief Fetching a `codecheck.yml` and resolving a DOI through OJS's HTTP
 *   client (#65): what reaches the caller, with Guzzle's mock handler in
 *   place of the network.
 */

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

// As in production, where the classes that use it load the plugin's own
// Composer autoloader first: Guzzle classes resolved from OJS's older copy
// before it would be mixed with the plugin's for every later test.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use APP\plugins\generic\codecheck\api\v1\CurlApiClient;
use APP\plugins\generic\codecheck\classes\Exceptions\CurlExceptions\CurlHttpException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;

class CurlApiClientUnitTest extends PKPTestCase
{
    private static function client(array $answers): CurlApiClient
    {
        return new CurlApiClient(new Client(['handler' => HandlerStack::create(new MockHandler($answers))]));
    }

    public function testFetchAnswersTheBody()
    {
        $this->assertSame('version: x', self::client([new Response(200, [], 'version: x')])->fetch('https://example.org/codecheck.yml'));
    }

    public function testFetchFollowsRedirects()
    {
        $client = self::client([
            new Response(302, ['Location' => 'https://cdn.example.org/codecheck.yml']),
            new Response(200, [], 'version: x'),
        ]);

        $this->assertSame('version: x', $client->fetch('https://example.org/codecheck.yml'));
    }

    public function testAnHttpErrorKeepsItsStatus()
    {
        try {
            self::client([new Response(404)])->fetch('https://example.org/codecheck.yml');
            $this->fail('A 404 was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testAHostThatDoesNotAnswerIs504()
    {
        $request = new Request('GET', 'https://example.org/codecheck.yml');
        try {
            self::client([new ConnectException('timed out', $request)])->fetch('https://example.org/codecheck.yml');
            $this->fail('A timeout was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(504, $e->getCode());
        }
    }

    public function testAnyOtherFailureIs502()
    {
        $request = new Request('GET', 'https://example.org/codecheck.yml');
        try {
            self::client([new TooManyRedirectsException('loop', $request)])->fetch('https://example.org/codecheck.yml');
            $this->fail('A redirect loop was not refused.');
        } catch (CurlHttpException $e) {
            $this->assertSame(502, $e->getCode());
        }
    }

    public function testADoiResolvesToWhereItLeads()
    {
        $client = self::client([
            new Response(302, ['Location' => 'https://zenodo.org/records/3750741']),
            new Response(200, [], '<html></html>'),
        ]);

        $this->assertSame('https://zenodo.org/records/3750741', $client->resolveDoi('10.5281/zenodo.3750741'));
    }

    /** Nothing is requested for an address that is not a DOI. */
    public function testAnAddressThatIsNotADoiIsKept()
    {
        $this->assertSame('https://github.com/a/b', self::client([])->resolveDoi('https://github.com/a/b'));
    }

    public function testADoiThatCannotBeResolvedIsKept()
    {
        $request = new Request('GET', 'https://doi.org/10.5281/zenodo.1');
        $client = self::client([new ConnectException('timed out', $request)]);

        $this->assertSame('10.5281/zenodo.1', $client->resolveDoi('10.5281/zenodo.1'));
    }
}
