<?php

namespace APP\plugins\generic\codecheck\api\v1;

interface ApiClientInterface
{
    public function fetch(string $url): string|bool;
}