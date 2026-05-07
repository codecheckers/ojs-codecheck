<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

class CodecheckYamlValidator {
    private string $yamlContent;

    public function __construct(string $yamlContent)
    {
        $this->yamlContent = $yamlContent;
    }

    public function validateYaml(): void
    {
        // This will throw a Symphony ParseException, if the YAML content is invalid
        Yaml::parse($this->yamlContent);
    }
}