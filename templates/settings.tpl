{**
 * templates/settings.tpl
 *
 * Copyright (c) 2025 CODECHECK Initiative
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
	action="{url router=$smarty.const.ROUTE_COMPONENT op='manage' category='generic' plugin=$pluginName verb='settings' save=true}"
>
	<!-- Always add the csrf token to secure your form -->
	{csrf}

	{fbvFormArea id="codecheckSettingsArea"}
		{* CODECHECK Settings Heading *}
		<h3 class="section-title">{translate key="plugins.generic.codecheck.settings.title"}</h3>
		<p class="section-description">{translate key="plugins.generic.codecheck.settings.description"}</p>

		{* Option to enable/ disable CODECHECK *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.enableCodecheck"}</label>
			</div>
			{fbvElement
				type="checkbox"
				id="codecheckEnabled"
				checked=$codecheckEnabled
				label="plugins.generic.codecheck.settings.enableCodecheck.description"
			}
		{/fbvFormSection}

		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>