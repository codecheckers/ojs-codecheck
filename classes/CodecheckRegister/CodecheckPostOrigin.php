<?php

/**
 * @file classes/CodecheckRegister/CodecheckPostOrigin.php
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * @class CodecheckPostOrigin
 *
 * @brief Where a post to the CODECHECK register comes from: the journal, its
 *        address, the OJS and plugin versions, and the signature that closes
 *        every text the plugin writes there.
 *
 * Everything the plugin posts appears under the token owner's GitHub account,
 * so without a signature nothing says it was written by software, or from which
 * journal — and several journals may share one register. signature() closes
 * every free-text post, in three places: CodecheckGithubRegisterIssue (the
 * issue body — signed there rather than in the client, because the prefilled
 * "new issue" link carries the body without passing through the client), and
 * CodecheckGithubRegisterApiClient's commentOnIssue() and deposit pull request.
 * A new free-text write belongs on that list. The issue's JSON block carries
 * the rest. Why it is one text per journal: CLAUDE.md, "Settings".
 */

namespace APP\plugins\generic\codecheck\classes\CodecheckRegister;

use APP\core\Application;
use APP\plugins\generic\codecheck\classes\Constants;
use APP\plugins\generic\codecheck\classes\Log\CodecheckLogger;
use APP\plugins\generic\codecheck\CodecheckPlugin;
use PKP\config\Config;
use PKP\context\Context;

class CodecheckPostOrigin
{
    /** Separates the signature from the text it closes: a Markdown rule. */
    public const SEPARATOR = "\n\n---\n";

    public function __construct(
        private readonly string $journalName,
        private readonly string $journalUrl,
        private readonly ?string $ojsVersion,
        private readonly ?string $pluginVersion,
        private readonly mixed $signatureText = null,
    ) {
    }

    /**
     * The origin of a post from this journal, read from the running OJS.
     */
    public static function fromContext(CodecheckPlugin $plugin, Context $context): self
    {
        return new self(
            (string) $context->getLocalizedName(),
            self::journalUrl(
                $context->getPath(),
                Config::getVar('general', 'base_url[' . $context->getPath() . ']') ?: null,
                (string) Config::getVar('general', 'base_url'),
                (bool) Config::getVar('general', 'restful_urls')
            ),
            self::ojsVersion(),
            $plugin->codeVersion(),
            $plugin->getSetting($context->getId(), Constants::CODECHECK_GITHUB_SIGNATURE),
        );
    }

    /**
     * The journal's address, built from the configuration the way OJS builds a
     * journal's base URL (`PKPRouter::_urlGetBaseAndContext()`): a
     * `base_url[<journal>]` override as it stands, else `base_url`, `/index.php`
     * unless URLs are restful, and the journal's path.
     *
     * Not from the request, which is what `Dispatcher::url()` reads: that takes
     * the host from the `Host` / `X-Forwarded-Host` header, and a status change —
     * which an assigned reviewer can trigger — would then let the sender choose
     * the address written into a public post. The configuration is the
     * administrator's, and the same on the command line.
     */
    public static function journalUrl(string $path, ?string $overriddenBaseUrl, string $baseUrl, bool $restfulUrls): string
    {
        if ($overriddenBaseUrl !== null && $overriddenBaseUrl !== '') {
            return rtrim($overriddenBaseUrl, '/');
        }

        return rtrim($baseUrl, '/') . ($restfulUrls ? '' : '/index.php') . '/' . rawurlencode($path);
    }

    /**
     * The separator and the signature text, ready to append to a post.
     *
     * The stored text if there is one, else the default; the journal's name and
     * address are filled in for `{$journal}` and `{$journalUrl}`.
     */
    public function signature(): string
    {
        $text = is_string($this->signatureText) ? trim($this->signatureText) : '';
        if ($text === '') {
            $text = Constants::CODECHECK_GITHUB_SIGNATURE_DEFAULT;
        }

        return self::SEPARATOR . strtr($text, [
            // Escaped, so a name like "Journal [Beta]" cannot break the link the
            // default text puts it in; GitHub renders each escape as the character.
            '{$journal}' => self::escapeMarkdown($this->journalName),
            '{$journalUrl}' => $this->journalUrl,
        ]);
    }

    /**
     * Text with the characters that would change its meaning in GitHub's
     * Markdown escaped; GitHub renders each escape as the character.
     */
    public static function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\\\\[\]`*_<>#|])/', '\\\\$1', $text);
    }

    /**
     * The journal's contact page, which a register reader is sent to when the
     * journal coordinates a codechecker the register cannot reach (#186). Built
     * from the configuration, as the journal's address is, for the same reason.
     */
    public function contactUrl(): string
    {
        return $this->journalUrl . '/about/contact';
    }

    public function getJournalName(): string
    {
        return $this->journalName;
    }

    /** The issue's JSON block's `journal` object, but for the submission. */
    public function journalMetadata(): array
    {
        return [
            'name' => $this->journalName,
            'url' => $this->journalUrl,
            'ojsVersion' => $this->ojsVersion,
        ];
    }

    /** The issue's JSON block's `plugin` object. */
    public function pluginMetadata(): array
    {
        return [
            'name' => Constants::CODECHECK_PLUGIN_NAME,
            'version' => $this->pluginVersion,
        ];
    }

    /** The OJS release, or null when it cannot be read. */
    private static function ojsVersion(): ?string
    {
        try {
            return Application::get()->getCurrentVersion()->getVersionString();
        } catch (\Throwable $e) {
            CodecheckLogger::warning('Could not read the OJS version for a register post: ' . $e->getMessage());
            return null;
        }
    }
}
