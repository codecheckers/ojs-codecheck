<?php

namespace APP\plugins\generic\codecheck\api\v1;

use APP\plugins\generic\codecheck\classes\Roles\CodecheckRole;

class ApiEndpoint
{
    private array $endpoint;

    public function __construct(array $endpointList, string $route, string $requestMethod) {
        foreach ($endpointList[$requestMethod] as $endpoint) {
            if($route == $endpoint['route']) {
                $this->endpoint = $endpoint;
                break;
            }
        }
    }

    public function getHandler(): array
    {
        return $this->endpoint['handler'];
    }

    public function getRole(): string
    {
        return $this->endpoint['role'];
    }
}