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

    /**
     * Where the badge takes a reader.
     *
     * The journal picks which of the two it prefers; the other stands in when
     * the preferred one is missing, because a badge that links nowhere is worse
     * than one that links to the second choice. Both may be missing — a check
     * recorded with neither an identifier nor a DOI — and then the caller gets
     * an empty string and renders the badge unlinked.
     *
     * @param string $certificate the identifier or URL recorded for the check
     * @param string $doiLink the certificate's DOI as a URL, if there is one
     */
    public function getCertificateUrl(string $certificate, string $doiLink = ''): string
    {
        $certificate = trim($certificate);

        // A journal that recorded the certificate as a URL means that URL.
        $register = filter_var($certificate, FILTER_VALIDATE_URL)
            ? $certificate
            : Constants::getRegisterCertificateUrl($certificate);

        return $this->getLinkTarget() === Constants::CODECHECK_BADGE_LINK_TARGET_DOI
            ? ($doiLink ?: $register)
            : ($register ?: $doiLink);
    }

    /**
     * Which of the two targets the journal prefers. Unset means the register:
     * it can be built from the identifier every check has, while a DOI is
     * recorded separately and is often not there at all.
     */
    public function getLinkTarget(): string
    {
        $target = (string) $this->getSetting(Constants::CODECHECK_BADGE_LINK_TARGET);

        return in_array($target, Constants::CODECHECK_BADGE_LINK_TARGETS, true)
            ? $target
            : Constants::CODECHECK_BADGE_LINK_TARGET_REGISTER;
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
