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
	
	<h1 id="codecheckSettingsTitle">{translate key="plugins.generic.codecheck.settings.title"}</h1>

	{fbvFormArea id="codecheckSettingsArea"}
		{* Option to enable/ disable CODECHECK *}
		{fbvFormSection
			list=true
			title="plugins.generic.codecheck.settings.enableCodecheck"
		}
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
			title="plugins.generic.codecheck.settings.authorAnonymity"
		}
			{fbvElement
				type="checkbox"
				id="authorAnonymity"
				checked=$authorNotAnonym
				label="plugins.generic.codecheck.settings.authorAnonymity.description"
			}
		{/fbvFormSection}

		{* Repository connection settings option *}
		{fbvFormSection
			list=true
			title="plugins.generic.codecheck.settings.githubRegisterRepository"
		}
			{fbvElement
				type="text"
				id="githubRegisterRepository"
				class="pkpFormField__input"
				value=$githubRegisterRepository
				placeholder="plugins.generic.codecheck.settings.githubRegisterRepository.description"
			}
		{/fbvFormSection}

		{fbvFormSection}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.githubLabels"}</label>
				<button type="button" id="addLabel" class="pkpButton btn-add">
					{translate key="plugins.generic.codecheck.settings.githubLabels.button.add"}
				</button>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.settings.githubLabels.description"}</label>
			<div id="labelListEmptyState" class="empty-state">
				{translate key="plugins.generic.codecheck.settings.githubLabels.emptyState"}
			</div>
			<div id="labelList"></div>
		{/fbvFormSection}

		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>