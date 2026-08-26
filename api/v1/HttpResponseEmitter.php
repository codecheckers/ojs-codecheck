<?php

namespace APP\plugins\generic\codecheck\api\v1;

/**
 * Writes the response to the HTTP client and ends the process.
 *
 * This is what the API did inline before the emitter existed, and it is what
 * every served request still does.
 */
class HttpResponseEmitter implements JsonResponseEmitter
{
    public function emit(JsonResponse $response): never
    {
        $response->constructResponse();
    }
}
