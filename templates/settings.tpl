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

{literal}
<script>
	$(function () {
		let label_index = $('#labelList .settingsLabelRow').length;

		function updateEmptyState() {
			if ($('#labelList .settingsLabelRow').length === 0) {
				$('#labelListEmptyState').show();
			} else {
				$('#labelListEmptyState').hide();
			}
		}

		// Initial state
		updateEmptyState();

		$('#addLabel').on('click', function () {
			$('#labelList').append(`
				<div class="settingsLabelRow">
					<input
						type="text"
						name="labels[${label_index}][name]"
						class="pkpFormField__input"
					/>
					<button type="button" class="remove pkpButton pkpButton--close">×</button>
				</div>
			`);
			label_index++;
			updateEmptyState();
		});

		$('#labelList').on('click', '.remove', function () {
			$(this).closest('.settingsLabelRow').remove();
			updateEmptyState();
		});
	});
</script>
{/literal}

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
		
		{* Author anonymity option *}

		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.authorAnonymity"}</label>
			</div>
			{fbvElement
				type="checkbox"
				id="authorAnonymity"
				checked=$authorAnonymity
				label="plugins.generic.codecheck.settings.authorAnonymity.description"
			}
		{/fbvFormSection}

		{* GitHub Personal Access Token option *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.github.personalAccessToken"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.settings.github.personalAccessToken.description"}</label>
			{fbvElement
				type="text"
				id="githubRegisterRepository"
				class="pkpFormField__input"
				value=$githubPersonalAccessToken
			}
		{/fbvFormSection}

		{* Repository connection settings option *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.github.registerRepository"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.settings.github.registerRepository.description"}</label>
			{fbvElement
				type="text"
				id="githubRegisterRepository"
				class="pkpFormField__input"
				value=$githubRegisterRepository|default:"https://github.com/codecheckers/testing-dev-register/"
			}
		{/fbvFormSection}

		{fbvFormSection}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.github.labels"}</label>
				<button type="button" id="addLabel" class="pkpButton btn-add">
					{translate key="plugins.generic.codecheck.settings.github.labels.button.add"}
				</button>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.settings.github.labels.description"}</label>
			<div id="labelListEmptyState" class="empty-state">
				{translate key="plugins.generic.codecheck.settings.github.labels.emptyState"}
			</div>
			<div id="labelList"></div>
		{/fbvFormSection}

		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>