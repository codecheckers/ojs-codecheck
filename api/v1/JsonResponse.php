<?php

namespace APP\plugins\generic\codecheck\api\v1;

// header for AJAX calls
define('INDEX_FILE_STARTED', true);
header('Content-Type: application/json');

class JsonResponse
{
    public function response(array $json_array, int $responseState): void
    {
        http_response_code($responseState);
        echo json_encode($json_array);
        exit;
    }
}
