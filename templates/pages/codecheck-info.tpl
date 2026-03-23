{**
 * templates/codecheck-page.tpl
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Display CODECHECK Info content
 *}

{include file="frontend/components/header.tpl" pageTitleTranslated=$title}

<div>
	<img
		src="https://codecheck.org.uk/img/codecheck_logo.svg"
		alt="CODECHECK Logo"
		height="87"
		width="100"
	/>
	{* TODO: Localize this *}
	<p>CODECHECK tackles one of the main challenges of computational research by supporting codecheckers with a workflow, guidelines and tools to evaluate computer programs underlying scientific papers. The independent time-stamped runs conducted by codecheckers will award a “certificate of executable computation” and increase availability, discovery and reproducibility of crucial artefacts for computational sciences. See <a href="https://codecheck.org.uk/#2021-07--f1000research-paper-on-codecheck-published-after-reviews-">the CODECHECK paper</a> for a full description of problems, solutions, and goals and take a look at the <a href="https://github.com/codecheckers">GitHub organisation</a> for examples of codechecks and the CODECHECK infrastructure and tools.</p>
	<p>CODECHECK is based on five principles which are described in detail in the <a href="https://codecheck.org.uk/project/">project description</a> and <a href="https://codecheck.org.uk/#2021-07--f1000research-paper-on-codecheck-published-after-reviews-">the paper</a>.</p>
	<ol>
		<li>Codecheckers record but don’t investigate or fix.</li>
		<li>Communication between humans is key.</li>
		<li>Credit is given to codecheckers.</li>
		<li>Workflows must be auditable.</li>
		<li>Open by default and transitional by disposition.</li>
	</ol>
	<p>The principles can be implemented in different <a href="https://codecheck.org.uk/process/">processes</a>, one of which is the <a href="https://codecheck.org.uk/guide/community-workflow-overview">CODECHECK community workflow</a>.</p>
	<p>To stay in touch with the project, follow us on social media at Mastodon logo <a href="https://fediscience.org/@codecheck">https://fediscience.org/@codecheck</a>.</p>
</div>

{include file="frontend/components/footer.tpl"}