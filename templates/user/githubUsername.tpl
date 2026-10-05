{**
 * templates/user/githubUsername.tpl
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the Apache License, Version 2.0. For full terms see the file LICENSE.
 *
 * The GitHub username on the public profile and the manager's user form (#13).
 *}
{fbvFormSection}
	{fbvElement type="text" label="plugins.generic.codecheck.githubUsername.label" name="githubUsername" id="githubUsername" value=$githubUsername}
	<p class="description">{translate key="plugins.generic.codecheck.githubUsername.description"}</p>
{/fbvFormSection}
