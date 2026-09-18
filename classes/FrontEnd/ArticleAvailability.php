<?php
/**
 * @file classes/FrontEnd/ArticleAvailability.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticleAvailability
 * @brief Renders the data and software availability statement on the article landing page.
 */

namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class ArticleAvailability
{
    /** @var CodecheckPlugin */
    public CodecheckPlugin $plugin;

    /** @param CodecheckPlugin $plugin */
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

        $heading = $this->getHeading($context->getId());
        $statement = $this->resolveStatement(
            $publication->getData('dataAvailabilityStatement'),
            $heading,
            $this->hidesEmptyStatement($context->getId())
        );

        if ($statement === null) {
            return false;
        }

        $templateMgr->assign([
            'codecheckAvailabilityHeading'   => $heading,
            'codecheckAvailabilityStatement' => $statement,
            // Lets a theme tell the author's words from our stand-in message.
            'codecheckAvailabilityProvided'  => trim((string) $publication->getData('dataAvailabilityStatement')) !== '',
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
     * Whether the statement is shown. Unset means show — the section is only
     * absent once a journal has switched it off.
     */
    public function isEnabled(int $contextId): bool
    {
        $setting = $this->plugin->getSetting($contextId, Constants::CODECHECK_SHOW_AVAILABILITY_STATEMENT);

        return $setting === null ? true : (bool) $setting;
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
     * The section heading, falling back to the localised default when the
     * journal has cleared the field rather than rendering an empty heading.
     */
    public function getHeading(int $contextId): string
    {
        $heading = trim((string) $this->plugin->getSetting(
            $contextId,
            Constants::CODECHECK_AVAILABILITY_STATEMENT_HEADING
        ));

        return $heading !== ''
            ? $heading
            : __('plugins.generic.codecheck.dataSoftwareAvailability');
    }
}
