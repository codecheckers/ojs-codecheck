<?php

namespace APP\plugins\generic\codecheck\api\v1;

// header for AJAX calls
define('INDEX_FILE_STARTED', true);
header('Content-Type: application/json');

class JsonResponse
{
    /**
     * This function creates a new JSON Response, echoes it to the HTML page it was calles upon and sets the according HTTP Response Code
     * 
     * @param $json_array The array that will be json encoded into the response
     * @param $responseState The HTTP Response Code that will be set accordingly
     * @return void
     */
    public function response(array $json_array, int $responseState): void
    {
        http_response_code($responseState);
        echo json_encode($json_array);
        exit;
    }
}
