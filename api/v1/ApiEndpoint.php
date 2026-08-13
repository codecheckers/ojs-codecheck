<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\classes\CodecheckRoles\CodecheckRoleArray;
use APP\plugins\generic\codecheck\classes\Exceptions\EndpointNotFoundException;

class ApiEndpoint
{
    private ?array $endpoint = null;

    /**
     * Look up the endpoint registered for a route and request method.
     *
     * OJS's own API router rejects paths and methods this table does not cover
     * before CodecheckApiHandler is reached, so an unresolved lookup should not
     * happen in a served request. It is still made explicit here: a method with
     * no endpoints at all no longer raises "Undefined array key", and reading an
     * unresolved endpoint reports what is wrong instead of failing on an
     * uninitialized property.
     *
     * @param array $endpointList Endpoints keyed by request method
     * @param string $route The route, without the api/v1/codecheck/ prefix
     * @param string $requestMethod The HTTP method of the request
     */
    public function __construct(array $endpointList, string $route, string $requestMethod)
    {
        foreach ($endpointList[$requestMethod] ?? [] as $endpoint) {
            if ($route === $endpoint['route']) {
                $this->endpoint = $endpoint;
                break;
            }
        }
    }

    /**
     * @throws EndpointNotFoundException when no endpoint matched
     */
    public function getHandler(): array
    {
        $this->assertFound();

        return $this->endpoint['handler'];
    }

    /**
     * @throws EndpointNotFoundException when no endpoint matched
     */
    public function getRoles(): CodecheckRoleArray
    {
        $this->assertFound();

        return $this->endpoint['roles'];
    }

    private function assertFound(): void
    {
        if ($this->endpoint === null) {
            throw new EndpointNotFoundException(
                'No CODECHECK API endpoint matched this route and request method.',
                404
            );
        }
    }
}
