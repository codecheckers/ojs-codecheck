<?php

namespace APP\plugins\generic\codecheck\tests;

use APP\plugins\generic\codecheck\classes\CodecheckRegister\GithubHttp;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/CodecheckRegisterUnitTests/GithubHttpUnitTest.php
 *
 * @class GithubHttpUnitTest
 *
 * @brief The time limits on GitHub calls, and the breaker that stops a request
 *   asking again once GitHub has failed to answer.
 */
class GithubHttpUnitTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GithubHttp::reset();
    }

    protected function tearDown(): void
    {
        // A static: left tripped, it would refuse every GitHub call in the
        // tests that follow.
        GithubHttp::reset();
        parent::tearDown();
    }

    private function send(MockHandler $handler): Response
    {
        $client = new GuzzleClient(GithubHttp::options($handler));

        return $client->sendRequest(new Request('GET', 'https://api.github.com/repos/o/r/issues/1'));
    }

    public function testEveryCallHasATimeLimit()
    {
        $options = GithubHttp::options();

        $this->assertSame(GithubHttp::TIMEOUT_SECONDS, $options['timeout']);
        $this->assertSame(GithubHttp::CONNECT_TIMEOUT_SECONDS, $options['connect_timeout']);
        $this->assertLessThan($options['timeout'], $options['connect_timeout']);
    }

    public function testAnAnswerTripsNothing()
    {
        // A refusal is an answer: GitHub is there, and the next call may succeed.
        $handler = new MockHandler([new Response(404), new Response(200)]);

        $this->assertSame(404, $this->send($handler)->getStatusCode());
        $this->assertFalse(GithubHttp::wasUnreachable());
        $this->assertSame(200, $this->send($handler)->getStatusCode());
    }

    public function testAFailureToAnswerStopsTheRestOfTheRequest()
    {
        $request = new Request('GET', 'https://api.github.com/');
        $handler = new MockHandler([
            new ConnectException('cURL error 28: Operation timed out', $request),
            new Response(200),
        ]);

        try {
            $this->send($handler);
            $this->fail('the time limit is reported');
        } catch (ConnectException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }
        $this->assertTrue(GithubHttp::wasUnreachable());

        try {
            $this->send($handler);
            $this->fail('GitHub is not asked again in the same request');
        } catch (ConnectException $e) {
            $this->assertStringContainsString('not asked again', $e->getMessage());
        }
        // The queued answer was never asked for.
        $this->assertCount(1, $handler);
    }

    public function testTheGithubClientIsBuiltOnTheseOptions()
    {
        $handler = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"name": "r"}')]);

        $repository = GithubHttp::client($handler)->api('repo')->show('o', 'r');

        $this->assertSame('r', $repository['name']);
        $this->assertCount(0, $handler);
    }
}
