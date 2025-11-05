<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

use APP\plugins\generic\codecheck\classes\Base\SubmissionWizard as BaseSubmissionWizard;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class CodecheckSubmissionWizard extends BaseSubmissionWizard
{
    public function __construct(CodecheckPlugin $plugin)
    {
        $this->fieldName = 'codecheck';
        parent::__construct($plugin);
    }
}