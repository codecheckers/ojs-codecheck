<?php
/**
 * @file classes/FrontEnd/Badge.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Badge
 * @brief What the journal's badge settings resolve to on the reader-facing pages.
 *
 * The article sidebar and the issue table of contents show the same badge and
 * had a copy each of the rules for picking it. They share this instead, so a
 * new badge type or a changed fallback cannot land in one place and not the
 * other.
 */

namespace APP\plugins\generic\codecheck\classes\FrontEnd;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\CodecheckPlugin;

class Badge
{
    /** @var CodecheckPlugin */
    private CodecheckPlugin $plugin;

    private int $contextId;

    public function __construct(CodecheckPlugin $plugin, int $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
    }

    /**
     * The badge image, or null when the journal shows text instead.
     *
     * A custom badge with no URL behind it also falls through to text rather
     * than rendering a broken image.
     */
    public function getUrl(): ?string
    {
        $base = Application::get()->getRequest()->getBaseUrl() . '/' . $this->plugin->getPluginPath();

        return match ($this->getType()) {
            'codecheck_logo' => $base . '/assets/img/codecheck_logo.svg',
            'custom'         => $this->getSetting(Constants::CODECHECK_BADGE_CUSTOM_URL) ?: null,
            'none'           => null,
            default          => $base . '/assets/img/codeworks-badge.png',
        };
    }

    /**
     * What is written where the image would be. Journals name the CODECHECK
     * differently, so the wording is theirs to set; cleared falls back to the
     * localised default rather than rendering nothing at all.
     */
    public function getText(): string
    {
        $text = trim((string) $this->getSetting(Constants::CODECHECK_BADGE_TEXT));

        return $text !== ''
            ? $text
            : __('plugins.generic.codecheck.badge.textOnly');
    }

    /**
     * The colour that text is written in. Anything that is not a hex colour
     * falls back to the default rather than reaching a style attribute, the
     * way OJS's own theme colour option guards itself (pkp/pkp-lib#11974).
     */
    public function getTextColor(): string
    {
        $color = trim((string) $this->getSetting(Constants::CODECHECK_BADGE_TEXT_COLOR));

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color)
            ? $color
            : Constants::CODECHECK_BADGE_TEXT_COLOR_DEFAULT;
    }

    /** The height the image is rendered at, as a ready-made style attribute. */
    public function getStyle(): string
    {
        $height = (int) ($this->getSetting(Constants::CODECHECK_BADGE_HEIGHT) ?: 24);

        return 'height:' . $height . 'px; width:auto;';
    }

    public function getType(): string
    {
        return (string) ($this->getSetting(Constants::CODECHECK_BADGE_TYPE) ?: 'codeworks');
    }

    private function getSetting(string $name)
    {
        return $this->plugin->getSetting($this->contextId, $name);
    }
}
