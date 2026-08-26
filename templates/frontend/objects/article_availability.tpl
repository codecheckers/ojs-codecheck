{**
 * templates/frontend/objects/article_availability.tpl
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Data and software availability statement, shown in the article's main
 *  column below the abstract. Matches the markup of the abstract section above
 *  it so it inherits the theme's styling rather than carrying its own.
 *}

<section class="item codecheck-availability{if !$codecheckAvailabilityProvided} codecheck-availability-none{/if}" data-testid="codecheck-article-availability">
	<h2 class="label">{$codecheckAvailabilityHeading|escape}</h2>
	{$codecheckAvailabilityStatement|strip_unsafe_html|nl2br}
</section>
