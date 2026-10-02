<?php

/**
 * @file classes/Workflow/CodecheckMetadataDestinations.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckMetadataDestinations
 *
 * @brief Where this journal sends a check's metadata, as the publication
 *  Metadata page tells editors (#34).
 *
 * Every destination listed here is one the plugin actually writes to; each is
 * on or off according to the journal's settings. The answer goes into an
 * inline script on the backend, so it carries booleans and public addresses
 * only — never the token or the ORCID client credentials it is derived from.
 */

namespace APP\plugins\generic\codecheck\classes\Workflow;

use APP\plugins\generic\codecheck\classes\Constants;

class CodecheckMetadataDestinations
{
    /** @var callable(string): mixed */
    private $readSetting;

    /**
     * @param callable(string): mixed $readSetting a setting by name, already
     *  resolved against its recorded default — `getSettingWithDefault()`
     * @param bool $registerDepositEnabled `CodecheckPlugin::isRegisterDepositEnabled()`,
     *  the single reader of that switch (#177)
     */
    public function __construct(callable $readSetting, private bool $registerDepositEnabled)
    {
        $this->readSetting = $readSetting;
    }

    /**
     * The destinations, in the order a check reaches them.
     *
     * @return list<array{id: string, enabled: bool, url: ?string}>
     */
    public function toArray(): array
    {
        $organization = trim((string) $this->setting(Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION));
        $repository = trim((string) $this->setting(Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY));
        $registerUrl = $organization !== '' && $repository !== ''
            ? "https://github.com/{$organization}/{$repository}"
            : null;
        return [
            [
                'id' => 'registerIssue',
                // The register is all a reservation asks for: without a token
                // the editor still opens the issue, through the prefilled link.
                'enabled' => $registerUrl !== null,
                'url' => $registerUrl,
                // Despite its name the setting is "show author names": the
                // issue carries them only when it is on.
                'authorNames' => (bool) $this->setting(Constants::CODECHECK_AUTHOR_ANONYMITY),
            ],
            [
                'id' => 'registerCsv',
                // The pull request is opened by the plugin, so it needs the
                // token as well — CodecheckRegisterDepositService refuses without.
                'enabled' => $this->registerDepositEnabled
                    && $registerUrl !== null
                    && $this->isSet(Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN),
                'url' => $registerUrl,
            ],
            [
                'id' => 'orcid',
                // OrcidDepositService refuses every deposit without both credentials.
                'enabled' => (bool) $this->setting(Constants::ORCID_ENABLED)
                    && $this->isSet(Constants::ORCID_CLIENT_ID)
                    && $this->isSet(Constants::ORCID_CLIENT_SECRET),
                'url' => null,
                // Unset is the sandbox, as for every other ORCID reader.
                'sandbox' => $this->setting(Constants::ORCID_API_TYPE) !== Constants::ORCID_API_TYPE_PRODUCTION,
            ],
            ['id' => 'articlePage', 'enabled' => (bool) $this->setting(Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR), 'url' => null],
            ['id' => 'availabilityStatement', 'enabled' => (bool) $this->setting(Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT), 'url' => null],
            ['id' => 'issueToc', 'enabled' => (bool) $this->setting(Constants::CODECHECK_SHOW_IN_TOC), 'url' => null],
        ];
    }

    private function setting(string $name): mixed
    {
        return ($this->readSetting)($name);
    }

    private function isSet(string $name): bool
    {
        return !empty($this->setting($name));
    }
}
