<?php

namespace APP\plugins\generic\codecheck\tests\ApiUnitTests;

use APP\plugins\generic\codecheck\api\v1\JsonResponse;
use PKP\tests\PKPTestCase;

/**
 * Every endpoint of the plugin API answers through a JsonResponse, so what it
 * makes of a payload is the shape every client sees.
 *
 * `constructResponse()` and `staticResponse()` are not covered: they send
 * headers, echo and `exit`, which would end the test process. Taking that seam
 * apart is the subject of the API handler testability plan.
 */
class JsonResponseUnitTest extends PKPTestCase
{
    public function testThePayloadIsTheEncodedArray()
    {
        $response = new JsonResponse(['success' => true, 'value' => 42], 200);

        $this->assertSame('{"success":true,"value":42}', $response->getPayload());
        $this->assertSame(['success' => true, 'value' => 42], $response->getPayloadArray());
        $this->assertSame(200, $response->getHttpResponseCode());
    }

    public function testSuccessIsReadFromThePayload()
    {
        $this->assertTrue((new JsonResponse(['success' => true], 200))->isSuccess());
        $this->assertFalse((new JsonResponse(['success' => false], 400))->isSuccess());
    }

    public function testAPayloadWithoutASuccessKeyIsNotASuccess()
    {
        // Several endpoints answer with an error array and no success key; a
        // caller asking isSuccess() must not read that as a success.
        $this->assertFalse((new JsonResponse(['error' => 'Submission not found'], 404))->isSuccess());
        $this->assertFalse((new JsonResponse([], 200))->isSuccess());
    }

    public function testTheHttpCodeIsIndependentOfThePayload()
    {
        // Nothing derives one from the other, so a mismatch is possible and is
        // pinned here rather than assumed away.
        $response = new JsonResponse(['success' => true], 500);

        $this->assertTrue($response->isSuccess());
        $this->assertSame(500, $response->getHttpResponseCode());
    }

    /**
     * Exception codes are not HTTP statuses: the plugin's own exceptions carry
     * 0 and a cURL error carries its error number, and passing either on made
     * the response itself fatal (#130).
     */
    public function testAnExceptionCodeThatIsNoHttpStatusBecomesAServerError()
    {
        $this->assertSame(500, JsonResponse::errorStatus(new \Exception('no code')));
        $this->assertSame(500, JsonResponse::errorStatus(new \Exception('cURL 7', 7)));
        $this->assertSame(500, JsonResponse::errorStatus(new \Exception('created', 201)));
        $this->assertSame(500, JsonResponse::errorStatus(new \Exception('nonsense', 99999)));
    }

    public function testARealErrorStatusIsKept()
    {
        $this->assertSame(404, JsonResponse::errorStatus(new \Exception('Not Found', 404)));
        $this->assertSame(403, JsonResponse::errorStatus(new \Exception('Forbidden', 403)));
        $this->assertSame(502, JsonResponse::errorStatus(new \Exception('Bad Gateway', 502)));
    }

    public function testNestedAndUnicodePayloadsSurviveTheRoundTrip()
    {
        $payload = [
            'success' => true,
            'codecheck' => [
                'manifest' => [['file' => 'figure 1.png', 'comment' => 'Figüre — 1']],
                'repository' => ['repositories' => null],
            ],
        ];

        $response = new JsonResponse($payload, 200);

        $this->assertSame($payload, $response->getPayloadArray());
    }
}
