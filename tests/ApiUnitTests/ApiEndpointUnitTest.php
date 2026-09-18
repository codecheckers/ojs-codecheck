<?php

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\api\v1\ApiEndpoint;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\Exceptions\EndpointNotFoundException;
use PKP\tests\PKPTestCase;

/**
 * @file APP/plugins/generic/codecheck/tests/ApiUnitTests/ApiEndpointUnitTest.php
 *
 * @class ApiEndpointUnitTest
 *
 * @brief Tests for the CODECHECK API endpoint lookup
 *
 * This is the routing table for api/v1/codecheck: every request the plugin
 * serves is dispatched through it, so which route and method map to which
 * handler and which role set is worth pinning down.
 *
 * OJS's own API router answers paths and methods this table does not cover
 * before CodecheckApiHandler runs — verified against the running instance —
 * so the unresolved cases below are about the class being total, not about a
 * reachable HTTP failure.
 */
class ApiEndpointUnitTest extends PKPTestCase
{
    private CodecheckRoleArray $readRoles;
    private CodecheckRoleArray $editRoles;
    private array $endpoints;

    protected function setUp(): void
    {
        parent::setUp();

        $this->readRoles = new CodecheckRoleArray([1]);
        $this->editRoles = new CodecheckRoleArray([16]);

        $this->endpoints = [
            'GET' => [
                ['route' => 'metadata', 'handler' => [$this, 'getMetadata'], 'roles' => $this->readRoles],
                ['route' => 'status/history', 'handler' => [$this, 'getHistory'], 'roles' => $this->readRoles],
            ],
            'POST' => [
                ['route' => 'metadata', 'handler' => [$this, 'saveMetadata'], 'roles' => $this->editRoles],
            ],
        ];
    }

    public function testResolvesARegisteredRoute()
    {
        $endpoint = new ApiEndpoint($this->endpoints, 'metadata', 'GET');

        $this->assertSame([$this, 'getMetadata'], $endpoint->getHandler());
        $this->assertSame($this->readRoles, $endpoint->getRoles());
    }

    /**
     * The same route means different things per method — GET metadata reads,
     * POST metadata writes, and they carry different roles.
     */
    public function testTheSameRouteResolvesPerRequestMethod()
    {
        $get = new ApiEndpoint($this->endpoints, 'metadata', 'GET');
        $post = new ApiEndpoint($this->endpoints, 'metadata', 'POST');

        $this->assertSame([$this, 'getMetadata'], $get->getHandler());
        $this->assertSame([$this, 'saveMetadata'], $post->getHandler());
        $this->assertSame($this->editRoles, $post->getRoles());
    }

    public function testResolvesARouteContainingASlash()
    {
        $endpoint = new ApiEndpoint($this->endpoints, 'status/history', 'GET');

        $this->assertSame([$this, 'getHistory'], $endpoint->getHandler());
    }

    public function testDoesNotResolveAnUnknownRoute()
    {
        $this->expectException(EndpointNotFoundException::class);

        (new ApiEndpoint($this->endpoints, 'does-not-exist', 'GET'))->getHandler();
    }

    /**
     * A method the table has no entries for at all used to raise "Undefined
     * array key" on the way to failing.
     */
    public function testDoesNotResolveAMethodTheApiDoesNotServe()
    {
        $this->expectException(EndpointNotFoundException::class);

        (new ApiEndpoint($this->endpoints, 'metadata', 'DELETE'))->getHandler();
    }

    public function testDoesNotResolveARouteRegisteredUnderAnotherMethod()
    {
        $this->expectException(EndpointNotFoundException::class);

        (new ApiEndpoint($this->endpoints, 'status/history', 'POST'))->getHandler();
    }

    /**
     * Exact matching, checked inline so a failure names the offending route.
     */
    public function testRouteMatchingIsExactRatherThanAPrefix()
    {
        foreach (['meta', 'metadata/extra', ''] as $route) {
            try {
                (new ApiEndpoint($this->endpoints, $route, 'GET'))->getHandler();
                $this->fail("route '{$route}' should not have matched 'metadata'");
            } catch (EndpointNotFoundException $expected) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Reading an unresolved endpoint says so. It used to throw "Typed property
     * ApiEndpoint::\$endpoint must not be accessed before initialization".
     */
    public function testReadingUnresolvedRolesReportsTheEndpointIsMissing()
    {
        $this->expectException(EndpointNotFoundException::class);

        (new ApiEndpoint($this->endpoints, 'does-not-exist', 'GET'))->getRoles();
    }
}
