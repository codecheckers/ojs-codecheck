<?php

/**
 * @file classes/Codecheckers/CodecheckerJournalSetup.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckerJournalSetup
 *
 * @brief What a journal needs to invite codecheckers as reviewers (#13): a
 *   "Codechecker" role at the Reviewer permission level, and an "Invitation to
 *   codecheck" email template that "Add Reviewer" offers beside "Review Request".
 *
 * Both belong to the journal once created: a manager may rename or edit them,
 * and may delete them. The plugin keeps the role's id and the template's key in
 * its settings, creates each once, when the plugin is enabled, and never again
 * on its own — the settings page shows when one is missing (deleted, or never
 * created because the plugin was enabled before this existed) and creates it
 * on request.
 */

namespace APP\plugins\generic\codecheck\classes\Codecheckers;

use APP\facades\Repo;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\mail\mailables\ReviewRequest;
use PKP\security\Role;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\UserGroup;

class CodecheckerJournalSetup
{
    public function __construct(private CodecheckPlugin $plugin)
    {
    }

    /**
     * Create whichever of the two the journal has never had: no setting means
     * never created. One that was created and later deleted is not created
     * again here, only by recreateMissing().
     *
     * Never throws: it runs while the plugin is enabled or a journal created,
     * which must not fail for it, and the settings page offers the rest.
     */
    public function installOnce(Context $context): void
    {
        $contextId = (int) $context->getId();

        if ($this->plugin->getSetting($contextId, Constants::CODECHECKER_USER_GROUP_ID) === null) {
            $this->logFailure(fn () => $this->createRole($context));
        }

        if ($this->plugin->getSetting($contextId, Constants::CODECHECK_INVITATION_TEMPLATE_KEY) === null) {
            $this->logFailure(fn () => $this->createTemplate($context));
        }
    }

    /**
     * Whether the role and the template the plugin created still exist.
     *
     * @return array{role: bool, template: bool}
     */
    public function status(int $contextId): array
    {
        $userGroupId = $this->plugin->getSetting($contextId, Constants::CODECHECKER_USER_GROUP_ID);
        $templateKey = $this->plugin->getSetting($contextId, Constants::CODECHECK_INVITATION_TEMPLATE_KEY);

        return [
            'role' => $userGroupId !== null && UserGroup::findById((int) $userGroupId, $contextId) !== null,
            'template' => is_string($templateKey) && $templateKey !== ''
                && Repo::emailTemplate()->getByKey($contextId, $templateKey) !== null,
        ];
    }

    /**
     * Create again whichever of the two is missing, and remember the new one.
     *
     * @return array{role: bool, template: bool} the status afterwards
     */
    public function recreateMissing(Context $context): array
    {
        $contextId = (int) $context->getId();
        $status = $this->status($contextId);

        if (!$status['role']) {
            $this->logFailure(fn () => $this->createRole($context));
        }
        if (!$status['template']) {
            $this->logFailure(fn () => $this->createTemplate($context));
        }

        // Read back rather than assumed, so a failure shows as still missing.
        return $this->status($contextId);
    }

    private function logFailure(callable $create): void
    {
        try {
            $create();
        } catch (\Throwable $e) {
            CodecheckLogger::error('Could not create the Codechecker role or the invitation template: ' . $e->getMessage());
        }
    }

    /**
     * A role at the Reviewer permission level in the review stage, open to
     * self-registration: on the registration form, it is its own checkbox,
     * which then asks for reviewing interests.
     */
    private function createRole(Context $context): void
    {
        $contextId = (int) $context->getId();

        // One transaction, so a failure leaves no role that no setting names,
        // which the next attempt would duplicate.
        DB::transaction(function () use ($context, $contextId) {
            $this->saveRole($context, $contextId);
        });

        Repo::userGroup()::forgetEditorialCache($contextId);
    }

    private function saveRole(Context $context, int $contextId): void
    {
        $userGroup = new UserGroup();
        $userGroup->roleId = Role::ROLE_ID_REVIEWER;
        $userGroup->contextId = $contextId;
        $userGroup->isDefault = false;
        $userGroup->showTitle = true;
        $userGroup->permitSelfRegistration = true;
        $userGroup->permitMetadataEdit = false;
        $userGroup->permitSettings = false;
        $userGroup->recommendOnly = false;
        $userGroup->masthead = false;
        $userGroup->name = self::localizedTexts($context, 'plugins.generic.codecheck.codecheckerRole.name');
        $userGroup->abbrev = self::localizedTexts($context, 'plugins.generic.codecheck.codecheckerRole.abbrev');
        $userGroup->save();

        UserGroupStage::create([
            'contextId' => $contextId,
            'userGroupId' => $userGroup->id,
            'stageId' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
        ]);

        $this->plugin->updateSetting($contextId, Constants::CODECHECKER_USER_GROUP_ID, (int) $userGroup->id, 'int');
    }

    /**
     * An alternate to "Review Request", so "Add Reviewer" lists it under
     * "Choose a predefined message" in every mode and round.
     */
    private function createTemplate(Context $context): void
    {
        // The body's `{$…}` variables survive translation: a message is only
        // substituted into when parameters are given, and none are.
        $template = Repo::emailTemplate()->newDataObject([
            'contextId' => (int) $context->getId(),
            'alternateTo' => ReviewRequest::getEmailTemplateKey(),
            'name' => self::localizedTexts($context, 'plugins.generic.codecheck.invitationTemplate.name'),
            'subject' => self::localizedTexts($context, 'plugins.generic.codecheck.invitationTemplate.subject'),
            'body' => self::localizedTexts($context, 'plugins.generic.codecheck.invitationTemplate.body'),
        ]);
        $key = Repo::emailTemplate()->add($template);

        $this->plugin->updateSetting((int) $context->getId(), Constants::CODECHECK_INVITATION_TEMPLATE_KEY, $key, 'string');
    }

    /**
     * A message in each of the journal's languages the plugin is translated
     * into, and in its primary language whether or not it is: there, the
     * English text stands in, so the role and the template always have a name.
     *
     * @return array<string, string> locale => text
     */
    private static function localizedTexts(Context $context, string $key, array $params = []): array
    {
        $translate = fn (string $locale) => __($key, $params, $locale);

        return self::textsIn(
            (array) $context->getSupportedLocales(),
            (string) $context->getPrimaryLocale(),
            $translate,
            fn (string $text) => $text !== '' && !str_starts_with($text, '##')
        );
    }

    /**
     * The rule of localizedTexts(), without the translator.
     *
     * @param callable(string): string $translate
     * @param callable(string): bool $isTranslated
     *
     * @return array<string, string>
     */
    public static function textsIn(array $locales, string $primaryLocale, callable $translate, callable $isTranslated): array
    {
        $texts = [];
        foreach (array_unique([...$locales, $primaryLocale]) as $locale) {
            $text = $translate($locale);
            if ($isTranslated($text)) {
                $texts[$locale] = $text;
            }
        }

        $texts[$primaryLocale] ??= $translate('en');

        return $texts;
    }
}
