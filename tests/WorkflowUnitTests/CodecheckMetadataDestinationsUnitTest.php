<?php

/**
 * @file tests/WorkflowUnitTests/CodecheckMetadataDestinationsUnitTest.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @brief Issue #34 — where a check's metadata goes, as the publication
 *        Metadata page lists it, read from the journal's settings.
 */

namespace APP\plugins\generic\codecheck\tests\WorkflowUnitTests;

use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Workflow\CodecheckMetadataDestinations;
use PKP\tests\PKPTestCase;

class CodecheckMetadataDestinationsUnitTest extends PKPTestCase
{
    private const TOKEN = 'github_pat_secret';

    private const CONFIGURED = [
        Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN => self::TOKEN,
        Constants::CODECHECK_GITHUB_REGISTER_ORGANIZATION => 'codecheckers',
        Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY => 'register',
        Constants::CODECHECK_AUTHOR_ANONYMITY => true,
        Constants::ORCID_ENABLED => true,
        Constants::ORCID_API_TYPE => Constants::ORCID_API_TYPE_PRODUCTION,
        Constants::ORCID_CLIENT_ID => 'APP-0000',
        Constants::ORCID_CLIENT_SECRET => 'orcid_secret',
        Constants::CODECHECK_SHOW_ARTICLE_SIDEBAR => true,
        Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT => true,
        Constants::CODECHECK_SHOW_IN_TOC => true,
    ];

    private function destinations(array $settings, bool $depositEnabled = true): array
    {
        $list = (new CodecheckMetadataDestinations(
            fn (string $name) => $settings[$name] ?? null,
            $depositEnabled,
            (bool) ($settings[Constants::ORCID_ENABLED] ?? false)
        ))->toArray();

        return array_column($list, null, 'id');
    }

    public function testListsEveryDestinationInOrder()
    {
        $this->assertSame(
            ['registerIssue', 'registerCsv', 'orcid', 'articlePage', 'availabilityStatement', 'issueToc'],
            array_keys($this->destinations(self::CONFIGURED))
        );
    }

    public function testAFullyConfiguredJournalHasEveryDestinationOn()
    {
        $destinations = $this->destinations(self::CONFIGURED);

        foreach ($destinations as $id => $destination) {
            $this->assertTrue($destination['enabled'], $id);
        }
        $this->assertSame('https://github.com/codecheckers/register', $destinations['registerIssue']['url']);
        $this->assertSame('https://github.com/codecheckers/register', $destinations['registerCsv']['url']);
        $this->assertTrue($destinations['registerIssue']['authorNames']);
        $this->assertFalse($destinations['orcid']['sandbox']);
    }

    public function testNothingConfiguredIsEveryDestinationOffWithoutAddresses()
    {
        $destinations = $this->destinations([], false);

        foreach ($destinations as $id => $destination) {
            $this->assertFalse($destination['enabled'], $id);
        }
        $this->assertNull($destinations['registerIssue']['url']);
        $this->assertNull($destinations['registerCsv']['url']);
        $this->assertFalse($destinations['registerIssue']['authorNames']);
    }

    /**
     * Without a token the plugin cannot open the register.csv pull request —
     * the deposit refuses — but an editor still opens the register issue
     * through the prefilled link, so that destination stays on.
     */
    public function testOnlyTheRegisterCsvDepositNeedsAToken()
    {
        $settings = self::CONFIGURED;
        unset($settings[Constants::CODECHECK_GITHUB_PERSONAL_ACCESS_TOKEN]);

        $destinations = $this->destinations($settings);

        $this->assertTrue($destinations['registerIssue']['enabled']);
        $this->assertFalse($destinations['registerCsv']['enabled']);
        $this->assertSame('https://github.com/codecheckers/register', $destinations['registerCsv']['url']);
    }

    /** OrcidDepositService refuses every deposit without both credentials. */
    public function testOrcidNeedsBothCredentials()
    {
        foreach ([Constants::ORCID_CLIENT_ID, Constants::ORCID_CLIENT_SECRET] as $credential) {
            $settings = self::CONFIGURED;
            unset($settings[$credential]);

            $this->assertFalse($this->destinations($settings)['orcid']['enabled'], $credential);
        }
    }

    /** The deposit opens its pull request against the register, so it needs one. */
    public function testTheRegisterCsvDepositNeedsARegister()
    {
        $settings = self::CONFIGURED;
        $settings[Constants::CODECHECK_GITHUB_REGISTER_REPOSITORY] = '  ';

        $destinations = $this->destinations($settings);

        $this->assertFalse($destinations['registerCsv']['enabled']);
        $this->assertFalse($destinations['registerIssue']['enabled']);
    }

    public function testTheRegisterCsvDepositFollowsItsSwitch()
    {
        $destinations = $this->destinations(self::CONFIGURED, false);

        $this->assertFalse($destinations['registerCsv']['enabled']);
        $this->assertTrue($destinations['registerIssue']['enabled']);
    }

    /** Unset reads as the sandbox, as every other ORCID reader does. */
    public function testOrcidIsTheSandboxUnlessProductionIsChosen()
    {
        $settings = self::CONFIGURED;
        unset($settings[Constants::ORCID_API_TYPE]);
        $this->assertTrue($this->destinations($settings)['orcid']['sandbox']);

        $settings[Constants::ORCID_API_TYPE] = Constants::ORCID_API_TYPE_SANDBOX;
        $this->assertTrue($this->destinations($settings)['orcid']['sandbox']);
    }

    public function testEachDisplaySwitchIsIndependent()
    {
        $settings = self::CONFIGURED;
        $settings[Constants::CODECHECK_SHOW_IN_TOC] = false;

        $destinations = $this->destinations($settings);

        $this->assertFalse($destinations['issueToc']['enabled']);
        $this->assertTrue($destinations['articlePage']['enabled']);
        $this->assertTrue($destinations['availabilityStatement']['enabled']);
    }

    /** The answer is written into an inline script on every backend page. */
    public function testNoSecretReachesTheOutput()
    {
        $json = json_encode($this->destinations(self::CONFIGURED));

        $this->assertStringNotContainsString(self::TOKEN, $json);
        $this->assertStringNotContainsString('orcid_secret', $json);
    }

    /** With OJS's own ORCID integration depositing reviews, the plugin's ORCID is no destination (#13). */
    public function testOrcidIsNoDestinationWhileOjsDepositsReviews(): void
    {
        $orcid = array_values(array_filter(
            (new CodecheckMetadataDestinations(
                fn (string $name) => [
                    Constants::ORCID_ENABLED => true,
                    Constants::ORCID_CLIENT_ID => 'APP-1',
                    Constants::ORCID_CLIENT_SECRET => 'secret',
                ][$name] ?? null,
                true,
                false
            ))->toArray(),
            fn (array $destination) => $destination['id'] === 'orcid'
        ))[0];

        $this->assertFalse($orcid['enabled']);
    }
}
