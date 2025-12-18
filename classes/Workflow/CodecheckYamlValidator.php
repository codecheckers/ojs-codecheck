<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class CodecheckYamlValidator {
    private string $yamlContent;
    private bool $isValidYaml;
    private ParseException $yamlParseException;

    public function __construct(string $yamlContent)
    {
        $this->yamlContent = $yamlContent;
        $this->isValidYaml = false;
        $this->yamlParseException = new ParseException("The Yaml File wasn't validated yet.");
    }

    public function validateYaml(): void
    {
        try {
            Yaml::parse($this->yamlContent);
            $this->isValidYaml = true;
            $this->yamlParseException = new ParseException("");
        } catch (ParseException $e) {
            $this->isValidYaml = false;
            $this->yamlParseException = $e;
        }
    }

    public function isValidYaml(): bool
    {
        return $this->isValidYaml;
    }

    public function getYamlParseException(): ParseException
    {
        return $this->yamlParseException;
    }
}