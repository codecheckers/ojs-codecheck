<?php

namespace APP\plugins\generic\codecheck\classes\Workflow;

require __DIR__ . '/../../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use \APP\core\Request;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataHandler;

class CodecheckYamlValidator {
    private string $yamlContent;

    public function __construct(string $yamlContent)
    {
        $this->yamlContent = $yamlContent;
    }

    public static function fromRequest(Request $request)
    {
        $codecheckMetadataHandler = new CodecheckMetadataHandler($request);
        $submissionId = $codecheckMetadataHandler->getSubmissionId();
        CodecheckLogger::debug("Submission ID during Yaml Validation: " . $submissionId);
        $result = $codecheckMetadataHandler->generateYaml($request, $submissionId);

        if(isset($result['error'])) {
            throw new \Exception("Something went wrong during the creation of the Yaml File from the CODECHECK Metadata.\n" . $result['error'], 404);
        }

        return new CodecheckYamlValidator($result['yaml']);
    }

    public function validateYaml(): void
    {
        // This will throw a Symphony ParseException, if the YAML content is invalid
        Yaml::parse($this->yamlContent);
    }
}