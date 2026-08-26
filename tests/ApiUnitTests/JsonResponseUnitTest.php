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
