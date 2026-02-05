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
		let label_index = 0;
		$('#addLabel').on('click', function () {
			$('#labelList').append(`
				<div class="settingsLabelRow">
					<input type="text" name="labels[${label_index}][name]">
					<button type="button" class="remove">✕</button>
				</div>
			`);
			label_index++;
		});

		$('.labelList').on('click', '.remove', function () {
			$(this).closest('.settingsLabelRow').remove();
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
				value=$githubRegisterRepository
				placeholder="plugins.generic.codecheck.settings.githubRegisterRepository.description"
			}
		{/fbvFormSection}

		{fbvFormSection
			title="plugins.generic.codecheck.settings.githubLabels"
			description="plugins.generic.codecheck.settings.githubLabels.description"
		}
			{fbvElement
				type="button"
				id="addLabel"
				label="plugins.generic.codecheck.settings.githubLabels.button.add"
			}
			<div id="labelList"></div>
		{/fbvFormSection}

		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}
	{fbvFormButtons submitText="common.save"}
</form>