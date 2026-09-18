<?php

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\core\Request;
use APP\journal\Journal;
use APP\plugins\generic\codecheck\api\v1\CodecheckApiHandler;
use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use APP\plugins\generic\codecheck\api\v1\JsonResponseEmitter;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleManager;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Exceptions\EndpointNotFoundException;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\user\User;
use Illuminate\Contracts\Session\Session;
use PKP\security\Role;
use PKP\tests\PKPTestCase;

/**
 * Carries the response out of the handler the way `exit` does in production.
 *
 * Endpoint bodies assume that emitting a response ends the request: several
 * carry on to further statements that must not run. A test emitter that
 * returned normally would exercise code no served request ever reaches, so this
 * one unwinds instead — which is also what the `never` on
 * JsonResponseEmitter::emit() requires.
 */
class ResponseEmitted extends \Exception
{
    public function __construct(public readonly JsonResponse $response)
    {
        parent::__construct('A response was emitted.');
    }
}

class RecordingEmitter implements JsonResponseEmitter
{
    public function emit(JsonResponse $response): never
    {
        throw new ResponseEmitted($response);
    }
}

/**
 * The CODECHECK API handler: the part of it that decides whether a request is
 * answered at all.
 *
 * The handler sits outside PKP's authorization policies — it is installed from
 * the `Dispatcher::dispatch` hook and checks the CSRF token and the user's
 * roles itself — so these two guards are the plugin's only protection for
 * nineteen endpoints, several of which write to the CODECHECK register.
 *
 * None of this could be tested before: the constructor resolved the route,
 * authorized and served, and serving ends in `exit`, so building a handler in a
 * test killed the test process. The request cycle is now `execute()`, and the
 * response goes through an emitter the test replaces.
 *
 * What is *not* here: the endpoint bodies. Nearly all of them reach the
 * database or GitHub in their first lines and are covered end to end by the
 * Cypress e2e specs. The one exception is the register-URL endpoint, used here
 * to show that an authorized request really is served.
 */
class CodecheckApiHandlerUnitTest extends PKPTestCase
{
    private const TOKEN = 'a-valid-csrf-token';
    private const CONTEXT_ID = 1;

    private ?string $previousCsrfHeader = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCsrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousCsrfHeader === null) {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        } else {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $this->previousCsrfHeader;
        }
        parent::tearDown();
    }

    /**
     * A request whose session holds TOKEN, for the given route and method.
     *
     * @param mixed $user null for a request with nobody logged in
     */
    private function request(string $route, string $method = 'GET', mixed $user = null): Request
    {
        $session = $this->createMock(Session::class);
        $session->method('token')->willReturn(self::TOKEN);

        $journal = $this->createMock(Journal::class);
        $journal->method('getId')->willReturn(self::CONTEXT_ID);

        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);
        $request->method('getUser')->willReturn($user);
        $request->method('getContext')->willReturn($journal);
        $request->method('getRequestPath')->willReturn('/index.php/codecheck/api/v1/codecheck/' . $route);
        $request->method('getRequestMethod')->willReturn($method);
        $request->method('getUserVar')->willReturn(null);

        return $request;
    }

    private function userWithRole(bool $hasRole): User
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturn($hasRole);

        return $user;
    }

    private function plugin(array $settings = []): CodecheckPlugin
    {
        $plugin = $this->createMock(CodecheckPlugin::class);
        $plugin->method('getSetting')->willReturnCallback(
            fn ($contextId, $name) => $settings[$name] ?? null
        );

        return $plugin;
    }

    private function roles(): CodecheckRoleManager
    {
        $admin = new CodecheckRoleArray([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]);
        $edit = new CodecheckRoleArray([$admin, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]);
        $read = new CodecheckRoleArray([$edit, Role::ROLE_ID_READER, Role::ROLE_ID_AUTHOR]);

        return new CodecheckRoleManager(readMetadata: $read, editMetadata: $edit, admin: $admin);
    }

    private function handler(Request $request, ?CodecheckPlugin $plugin = null): CodecheckApiHandler
    {
        return new CodecheckApiHandler(
            $plugin ?? $this->plugin(),
            $request,
            $this->roles(),
            new RecordingEmitter()
        );
    }

    /** Runs the request cycle and returns the response it ended with. */
    private function emitted(CodecheckApiHandler $handler): JsonResponse
    {
        try {
            $handler->execute();
        } catch (ResponseEmitted $emitted) {
            return $emitted->response;
        }

        $this->fail('The request ended without emitting a response.');
    }

    public function testRoutesAreReadFromTheRequestPath()
    {
        $this->assertSame('metadata', CodecheckApiHandler::routeFromPath('/index.php/journal/api/v1/codecheck/metadata'));
        $this->assertSame(
            'status/history',
            CodecheckApiHandler::routeFromPath('/index.php/journal/api/v1/codecheck/status/history'),
            'a route with a slash in it is one route, not a route plus an argument'
        );
    }

    public function testAPathThisPluginDoesNotServeHasNoRoute()
    {
        // OJS's own API router answers these before the plugin is reached; this
        // holds that the plugin does not claim them by accident.
        $this->assertNull(CodecheckApiHandler::routeFromPath('/index.php/journal/api/v1/submissions/8'));
        $this->assertNull(CodecheckApiHandler::routeFromPath('/index.php/journal/article/view/5'));
    }

    public function testConstructingAHandlerDoesNotAnswerAnything()
    {
        // The whole point of the refactor: this used to authorize and serve, and
        // serving ends the process.
        $this->expectNotToPerformAssertions();
        $this->handler($this->request('metadata'));
    }

    public function testARequestWithoutACsrfTokenIsRefused()
    {
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $response = $this->emitted($this->handler($this->request('metadata')));

        $this->assertSame(400, $response->getHttpResponseCode());
        $this->assertFalse($response->isSuccess());
        $this->assertSame('No or wrong CSRF Token', $response->getPayloadArray()['error']);
    }

    public function testARequestWithTheWrongCsrfTokenIsRefused()
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'someone-elses-token';

        $response = $this->emitted($this->handler($this->request('metadata')));

        $this->assertSame(400, $response->getHttpResponseCode());
        $this->assertSame('No or wrong CSRF Token', $response->getPayloadArray()['error']);
    }

    public function testTheTokenIsCheckedBeforeTheRouteIsResolved()
    {
        // A caller with no token learns nothing about which routes exist: an
        // unknown route without a token answers "No or wrong CSRF Token", not
        // "no such endpoint".
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $response = $this->emitted($this->handler($this->request('there/is/no/such/route')));

        $this->assertSame('No or wrong CSRF Token', $response->getPayloadArray()['error']);
    }

    public function testARequestFromNobodyIsRefused()
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::TOKEN;

        $response = $this->emitted($this->handler($this->request('metadata')));

        $this->assertSame(400, $response->getHttpResponseCode());
        $this->assertStringContainsString('no assigned Role', $response->getPayloadArray()['error']);
    }

    public function testAUserWithoutOneOfTheEndpointsRolesIsRefused()
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::TOKEN;

        $request = $this->request('metadata', 'GET', $this->userWithRole(false));
        $response = $this->emitted($this->handler($request));

        $this->assertSame(400, $response->getHttpResponseCode());
        $this->assertStringContainsString('right roles assigned', $response->getPayloadArray()['error']);
    }

    public function testAnAuthorizedRequestIsServed()
    {
        // The register URL endpoint reads two settings and nothing else, so it
        // is the one endpoint whose body runs here. What it proves is the rest
        // of the cycle: token accepted, route resolved, role accepted, and the
        // endpoint's own handler actually called.
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::TOKEN;

        $plugin = $this->plugin([
            Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION => 'codecheckers',
            Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY => 'register',
        ]);
        $request = $this->request('register', 'GET', $this->userWithRole(true));

        $response = $this->emitted($this->handler($request, $plugin));

        $this->assertSame(200, $response->getHttpResponseCode());
        $this->assertTrue($response->isSuccess());
        $this->assertSame('github.com/codecheckers/register', $response->getPayloadArray()['url']);
    }

    public function testTheMethodIsPartOfTheRoute()
    {
        // `identifier` is a POST route. Asking for it with GET resolves to no
        // endpoint, the same as an unknown route would.
        $_SERVER['HTTP_X_CSRF_TOKEN'] = self::TOKEN;

        $request = $this->request('identifier', 'GET', $this->userWithRole(true));

        $this->expectException(EndpointNotFoundException::class);
        $this->handler($request)->execute();
    }

    public function testEveryRegisteredEndpointCanActuallyBeServed()
    {
        // The endpoint table names handler methods as [$this, 'name'] strings,
        // which nothing checks until the route is requested. A renamed or
        // deleted method would otherwise only show up as a 500 in production.
        $handler = $this->handler($this->request('metadata'));

        $endpoints = (new \ReflectionClass($handler))->getProperty('endpoints');
        $endpoints->setAccessible(true);

        foreach ($endpoints->getValue($handler) as $method => $routes) {
            $seen = [];

            foreach ($routes as $endpoint) {
                // Most handler methods are private, so is_callable() from out
                // here says nothing; ask the class whether the method exists.
                [$target, $methodName] = $endpoint['handler'];
                $this->assertTrue(
                    method_exists($target, $methodName),
                    sprintf('%s %s names handler method %s, which does not exist', $method, $endpoint['route'], $methodName)
                );
                $this->assertInstanceOf(CodecheckRoleArray::class, $endpoint['roles']);
                $this->assertNotContains(
                    $endpoint['route'],
                    $seen,
                    sprintf('%s %s is registered twice; only the first would ever be reached', $method, $endpoint['route'])
                );
                $seen[] = $endpoint['route'];
            }
        }
    }
}
