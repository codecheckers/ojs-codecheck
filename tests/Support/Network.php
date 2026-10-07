<?php

/**
 * @file tests/Support/Network.php
 *
 * @class Network
 *
 * @brief The network of the tests that do not talk to GitHub (#191): Laravel's
 *   HTTP client answering by address, and refusing an address nobody answered
 *   instead of reaching the real one.
 */

namespace APP\plugins\generic\codecheck\tests\Support;

use Illuminate\Http\Client\Factory;

class Network
{
    /**
     * A Factory that answers the given addresses (`Factory::fake()` patterns)
     * and fails on any other request.
     *
     * @param array<string, mixed> $answers
     */
    public static function factory(array $answers = []): Factory
    {
        $http = new Factory();
        $http->preventStrayRequests();
        $http->fake($answers);

        return $http;
    }
}
