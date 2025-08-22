{**
 * templates/settings.tpl
 *
 * Copyright (c) 2024 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form for the CODECHECK plugin.
 *}
<script>
	$(function() {ldelim}
		$('#codecheckSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="codecheckSettings"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	<!-- Always add the csrf token to secure your form -->
	{csrf}
	
	<div class="pkp_form_description">
		<p>{translate key="plugins.generic.codecheck.settings.description"}</p>
	</div>

	{fbvFormArea id="codecheckSettingsArea"}
		{fbvFormSection}
			{fbvElement
				type="checkbox"
				id="enableCodecheck"
				checked=$enableCodecheck
				label="plugins.generic.codecheck.settings.enableCodecheck"
				description="plugins.generic.codecheck.settings.enableCodecheck.description"
			}
		{/fbvFormSection}
		
		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Repository connection settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>