<?php

namespace APP\plugins\generic\codecheck\api\v1;

/**
 * Sends a `JsonResponse` back to the caller and ends the request.
 *
 * The plugin's API answers by writing a response and stopping — every endpoint
 * body is written on the assumption that emitting a response is the last thing
 * that happens, and returns immediately afterwards without unwinding to a
 * central point.
 *
 * That contract is what `never` records here: an implementation must not return
 * to its caller. The production one exits; a test one throws. Anything that
 * returned normally would let an endpoint carry on past the response it just
 * sent and emit a second one.
 */
interface JsonResponseEmitter
{
    public function emit(JsonResponse $response): never;
}
