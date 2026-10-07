<?php

/**
 * @file classes/Codecheckers/CodecheckerNeededEmail.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerNeededEmail
 *
 * @brief The email telling an editor assigned to a submission that takes part
 *   in a CODECHECK that it has no codechecker yet (#31). Its default template
 *   is in `emailTemplates.xml`; the journal edits it under Emails.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;

class CodecheckerNeededEmail extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'plugins.generic.codecheck.codecheckerNeededEmail.name';
    protected static ?string $description = 'plugins.generic.codecheck.codecheckerNeededEmail.description';
    protected static ?string $emailTemplateKey = 'CODECHECK_CODECHECKER_NEEDED';
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = CodecheckerNeededNotice::EDITOR_ROLE_IDS;

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
