<?php

/**
 * @file classes/FrontEnd/ArticleAvailability.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class ArticleAvailability
 *
 * @brief Renders the data and software availability statement on the article landing page.
 */

namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\context\Context;
use PKP\facades\Locale;

class ArticleAvailability
{
    public CodecheckPlugin $plugin;

    public function __construct(CodecheckPlugin &$plugin)
    {
        $this->plugin = &$plugin;
    }

    /**
     * Append the availability statement to the article's main column.
     *
     * Hooked onto `Templates::Article::Main`, which fires inside `.main_entry`
     * directly after the abstract — a different hook from the
     * `Templates::Article::Details` one the sidebar uses, and the reason this
     * needs no template override.
     */
    public function addAvailabilityStatement(string $hookName, array $params): bool
    {
        $templateMgr = $params[1];
        $output = &$params[2];

        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return false;
        }

        if (!$this->isEnabled($context->getId())) {
            return false;
        }

        $publication = $templateMgr->getTemplateVars('publication');
        if (!$publication) {
            return false;
        }

        $heading = $this->getHeading($context);
        $statement = $this->resolveStatement(
            $publication->getData('dataAvailabilityStatement'),
            $heading,
            $this->hidesEmptyStatement($context->getId())
        );

        if ($statement === null) {
            return false;
        }

        $templateMgr->assign([
            'codecheckAvailabilityHeading' => $heading,
            'codecheckAvailabilityStatement' => $statement,
            // Lets a theme tell the author's words from our stand-in message.
            'codecheckAvailabilityProvided' => trim((string) $publication->getData('dataAvailabilityStatement')) !== '',
        ]);

        $output .= $templateMgr->fetch(
            $this->plugin->getTemplateResource('frontend/objects/article_availability.tpl')
        );

        return false;
    }

    /**
     * The text to render, or null when the section is left out altogether.
     *
     * An article without a statement says so by default: silence is
     * indistinguishable from a journal that does not ask for one, while an
     * explicit "none provided" tells a reader the question was put to the
     * author. A journal that would rather show nothing sets
     * CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT.
     *
     * @param string|null $stored the statement recorded on the publication
     * @param string $heading the section heading, which the message names
     * @param bool $hideWhenEmpty whether an empty statement omits the section
     */
    public function resolveStatement(?string $stored, string $heading, bool $hideWhenEmpty): ?string
    {
        $statement = trim((string) $stored);

        if ($statement !== '') {
            return $statement;
        }

        if ($hideWhenEmpty) {
            return null;
        }

        return __('plugins.generic.codecheck.availabilityStatement.none', ['heading' => $heading]);
    }

    /**
     * Whether the statement is shown, resolved against the default recorded in
     * `Constants::CODECHECK_SETTING_DEFAULTS`.
     */
    public function isEnabled(int $contextId): bool
    {
        return (bool) $this->plugin->getSettingWithDefault($contextId, Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT);
    }

    /**
     * Whether an article with no statement omits the section entirely. Unset
     * means no: the default is to say that none was provided.
     */
    public function hidesEmptyStatement(int $contextId): bool
    {
        return (bool) $this->plugin->getSetting(
            $contextId,
            Constants::CODECHECK_HIDE_EMPTY_AVAILABILITY_STATEMENT
        );
    }

    /**
     * The section heading in the reader's language — see
     * Constants::localizedText() — or the localised default, rather than an
     * empty heading.
     *
     * @param string|null $locale the reader's; the current one unless given
     */
    public function getHeading(Context $context, ?string $locale = null): string
    {
        return Constants::localizedText(
            $this->plugin->getSetting((int) $context->getId(), Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING),
            $locale ?? Locale::getLocale(),
            (string) $context->getPrimaryLocale()
        ) ?? __('plugins.generic.codecheck.dataSoftwareAvailability');
    }
}
