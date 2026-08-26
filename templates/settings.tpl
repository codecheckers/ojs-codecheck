{**
 * templates/settings.tpl
 *
 * Copyright (c) 2026 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings form for the CODECHECK plugin.
 *}

<script>
	$(function() {ldelim}
		$('#codecheckSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<style>
	.badge-options { display: flex; flex-direction: column; gap: 0.50rem; margin-top: 0.5rem; }
	.badge-option { display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; }
	.badge-option input[type="radio"] { margin-top: 3px; flex-shrink: 0; }
	.badge-options label { font-weight: normal; font-size: 0.9rem; }
	.badge-hint { display: block; font-size: 12px; color: #666; font-style: italic; margin-top: 2px; }
</style>

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
						name="githubCustomLabels[${label_index}]"
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

	function resetGitHubRegisterRepository() {
		$('input[name="githubRegisterOrganization"]').val("codecheckers");
		$('input[name="githubRegisterRepository"]').val("testing-dev-register");
	}

	$(function () {
		$('#resetSchema').on('click', function () {
			if (!confirm('Are you sure, you want to permanently delete all records in the CODECHECK Metadata DB Table?')) {
				return;
			}
			let resetSchemaUrl = $(this).data('url');
			$.post(
				resetSchemaUrl,
				{ csrfToken: pkp.currentUser.csrfToken },
				function(response) {
					alert('Finished resetting the CODECHECK Metadata DB.');
				}
			);
		});
	});
	
	// Keep the hex beside the swatch honest while the picker is being used.
	$(function () {
		var $picker = $('#codecheckBadgeTextColor');
		$picker.on('input change', function () {
			$('.badge-color-value').text($picker.val());
		});
	});

	// Each badge type may bring one field with it: a URL for a custom image, the
	// wording for text only.
	function toggleCustomBadgeUrl() {
		var selected = document.querySelector('input[name="codecheckBadgeType"]:checked');
		var type = selected ? selected.value : '';

		document.getElementById('customBadgeUrlSection').style.display =
			(type === 'custom') ? 'block' : 'none';
		document.getElementById('badgeTextSection').style.display =
			(type === 'none') ? 'block' : 'none';
	}

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
		
		{* Option to show or hide the CODECHECK block on published article pages *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.showArticleSidebar"}</label>
			</div>
			{fbvElement
				type="checkbox"
				id="showArticleSidebar"
				checked=$showArticleSidebar
				label="plugins.generic.codecheck.settings.showArticleSidebar.description"
			}
		{/fbvFormSection}

		{* Option to show or hide the CODECHECK badge in issue tables of contents *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.showInTOC"}</label>
			</div>
			{fbvElement
				type="checkbox"
				id="showInTOC"
				checked=$showInTOC
				label="plugins.generic.codecheck.settings.showInTOC.description"
			}
		{/fbvFormSection}

		{* Everything about the data and software availability statement on the
		   article landing page *}
		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_title">{translate key="plugins.generic.codecheck.settings.availability.title"}</label>
			</div>
			{* Data and software availability statement below the abstract *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.showAvailabilityStatement"}</label>
				</div>
				{fbvElement
					type="checkbox"
					id="showAvailabilityStatement"
					checked=$showAvailabilityStatement
					label="plugins.generic.codecheck.settings.showAvailabilityStatement.description"
				}
			{/fbvFormSection}

			{* What an article with no statement shows *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.hideEmptyAvailabilityStatement"}</label>
				</div>
				{fbvElement
					type="checkbox"
					id="hideEmptyAvailabilityStatement"
					checked=$hideEmptyAvailabilityStatement
					label="plugins.generic.codecheck.settings.hideEmptyAvailabilityStatement.description"
				}
			{/fbvFormSection}

			{* Heading for that section, so a journal can call it something else *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.availabilityStatementHeading"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.availabilityStatementHeading.description"}</label>
				{fbvElement
					type="text"
					id="availabilityStatementHeading"
					value=$availabilityStatementHeading
					placeholder="plugins.generic.codecheck.dataSoftwareAvailability"
				}
			{/fbvFormSection}
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_title">{translate key="plugins.generic.codecheck.settings.submission.title"}</label>
			</div>
			{* Show CODECHECK column in submissions dashboard *}
			{fbvFormSection list=true}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.showDashboardColumn"}</label>
				</div>
				{fbvElement
					type="checkbox"
					id="showDashboardColumn"
					checked=$showDashboardColumn
					label="plugins.generic.codecheck.settings.showDashboardColumn.description"
				}
			{/fbvFormSection}
			
			{* Setting for different CODECHECK modes *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.mode"}</label>
				</div>
				{fbvElement
					type="select"
					id="codecheckMode"
					class="codecheck-form-select"
					from=$codecheckModes
					selected=$codecheckMode
					translate=false
				}
			{/fbvFormSection}

			{* Which codecheck.yml config versions the metadata form offers *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.configVersions"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.configVersions.description"}</label>
				<fieldset class="codecheck-choice-list">
					{foreach from=$codecheckConfigVersions item=configVersion}
						<div class="codecheck-choice">
							<input
								type="checkbox"
								name="codecheckEnabledConfigVersions[]"
								id="configVersion-{$configVersion|escape}"
								value="{$configVersion|escape}"
								{if in_array($configVersion, $codecheckEnabledConfigVersions)}checked{/if}
							/>
							<label for="configVersion-{$configVersion|escape}">{$configVersion|escape}</label>
						</div>
					{/foreach}
				</fieldset>
			{/fbvFormSection}
		{/fbvFormSection}

		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_title">{translate key="plugins.generic.codecheck.settings.github.title"}</label>
			</div>
			{* GitHub Personal Access Token option *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.github.personalAccessToken"}</label>
				</div>
				<label class="description">
					{translate key="plugins.generic.codecheck.settings.github.personalAccessToken.description" patGuideUrl="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-personal-access-token-classic"}
				</label>
				<input 
					type="password"
					name="githubPersonalAccessToken"
					class="pkpFormField__input"
					value="{$githubPersonalAccessToken|escape}"
				/>
			{/fbvFormSection}

			{* Repository connection settings option *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.github.registerRepository"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.github.registerRepository.description"}</label>
				<div class="pkp_form_input_with_button_row">
					<div id="githubRegisterInputSection">
						<div>https://github.com/</div>
						<input
							type="text"
							name="githubRegisterOrganization"
							class="pkpFormField__input"
							value="{$githubRegisterOrganization|escape|default:'codecheckers'}"
						/>
						<div>/</div>
						<input
							type="text"
							name="githubRegisterRepository"
							class="pkpFormField__input"
							value="{$githubRegisterRepository|escape|default:'testing-dev-register'}"
						/>
					</div>
					<button
					type="button"
					class="pkpButton btn-remove"
					onclick="resetGitHubRegisterRepository()"
					>
					{translate key="plugins.generic.settings.button.reset"}
					</button>
				</div>
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

			{* Add Custom GitHub Labels *}
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
				<div id="labelList">
					{foreach from=$githubCustomLabels item=label key=index}
						<div class="settingsLabelRow">
							<input
								type="text"
								name="githubCustomLabels[{$index}]"
								class="pkpFormField__input"
								value="{$label|escape}"
							/>
							<button type="button" class="remove pkpButton pkpButton--close">×</button>
						</div>
					{/foreach}
				</div>
			{/fbvFormSection}

			{* Select which parts of the codecheck GitHub Issue are updated *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">Update the GitHub Issue</label>
				</div>
				<label class="description">Select which information should be updated in the GitHub Register Issue of the CODECHECK</label>
				{fbvElement
					type="checkbox"
					id="updateTitle"
					checked=$updateTitle
					label="plugins.generic.codecheck.settings.updateIssue.title"
				}
				{fbvElement
					type="checkbox"
					id="updateBody"
					checked=$updateBody
					label="plugins.generic.codecheck.settings.updateIssue.body"
				}
				{fbvElement
					type="checkbox"
					id="updateStatus"
					checked=$updateStatus
					label="plugins.generic.codecheck.settings.updateIssue.status"
				}
			{/fbvFormSection}
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_title">{translate key="plugins.generic.codecheck.settings.publication.title"}</label>
			</div>
			{* Block Publication, when CODECHECK has specific status *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.status"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.status.description"}</label>
				<fieldset class="codecheck-choice-list">
					{foreach from=$codecheckStatuses item=statusKey}
						<div class="codecheck-choice">
							<input
								type="checkbox"
								name="codecheckStatusKeysSelected[]"
								id="status-{$statusKey}"
								value="{$statusKey|escape}"
								{if $codecheckStatusKeysSelected && in_array($statusKey, $codecheckStatusKeysSelected)}checked{/if}
							/>
							<label for="status-{$statusKey}">{translate key=$statusKey}</label>
						</div>
					{/foreach}
				</fieldset>
			{/fbvFormSection}
			{* Enable extended validation of the CODECHECK metadata to block the publication of the submission *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.publication.extendedValidation.title"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.publication.extendedValidation.description"}</label>
				{fbvElement
					type="checkbox"
					id="codecheckPublicationExtendedValidation"
					checked=$codecheckPublicationExtendedValidation
					label="plugins.generic.codecheck.settings.publication.extendedValidation.checkboxText"
				}
			{/fbvFormSection}

			{* Enable automatic register.csv deposit on publication (Issue #10) *}
			{fbvFormSection
				list=true
			}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.registerDeposit.title"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.registerDeposit.description"}</label>
				{fbvElement
					type="checkbox"
					id="codecheckRegisterDepositEnabled"
					checked=$codecheckRegisterDepositEnabled
					label="plugins.generic.codecheck.settings.registerDeposit.checkboxText"
				}
			{/fbvFormSection}
		{/fbvFormSection}

		{* Badge / Logo — inside the form area, so it gets the same box as every
		   other group rather than rendering bare at the end of the form *}
		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_title">{translate key="plugins.generic.codecheck.settings.badge.title"}</label>
			</div>

			{fbvFormSection list=true}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.image"}</label>
				</div>
				<p class="description">{translate key="plugins.generic.codecheck.settings.badge.description"}</p>

				<div class="badge-options">
					<div class="badge-option">
						<input type="radio" id="badgeCodeworks" name="codecheckBadgeType" value="codeworks"
							{if $codecheckBadgeType == 'codeworks' || !$codecheckBadgeType}checked{/if}
							onchange="toggleCustomBadgeUrl()" />
						<label for="badgeCodeworks">{translate key="plugins.generic.codecheck.settings.badge.codeworks"}</label>
					</div>

					<div class="badge-option">
						<input type="radio" id="badgeCodecheckLogo" name="codecheckBadgeType" value="codecheck_logo"
							{if $codecheckBadgeType == 'codecheck_logo'}checked{/if}
							onchange="toggleCustomBadgeUrl()" />
						<label for="badgeCodecheckLogo">{translate key="plugins.generic.codecheck.settings.badge.codecheck_logo"}</label>
					</div>

					<div class="badge-option">
						<input type="radio" id="badgeCustom" name="codecheckBadgeType" value="custom"
							{if $codecheckBadgeType == 'custom'}checked{/if}
							onchange="toggleCustomBadgeUrl()" />
						<div>
							<label for="badgeCustom">{translate key="plugins.generic.codecheck.settings.badge.custom"}</label>
							<span class="badge-hint">{translate key="plugins.generic.codecheck.settings.badge.custom.hint"}</span>
						</div>
					</div>

					<div class="badge-option">
						<input type="radio" id="badgeNone" name="codecheckBadgeType" value="none"
							{if $codecheckBadgeType == 'none'}checked{/if}
							onchange="toggleCustomBadgeUrl()" />
						<label for="badgeNone">{translate key="plugins.generic.codecheck.settings.badge.none"}</label>
					</div>

					<div id="badgeTextSection" class="badge-dependent-field"{if $codecheckBadgeType != 'none'} style="display:none"{/if}>
						<label class="pkp_form_label" for="codecheckBadgeText">{translate key="plugins.generic.codecheck.settings.badge.text.label"}</label>
						<label class="description">{translate key="plugins.generic.codecheck.settings.badge.text.description"}</label>
						<input
							type="text"
							id="codecheckBadgeText"
							name="codecheckBadgeText"
							class="pkpFormField__input"
							value="{$codecheckBadgeText|escape}"
							placeholder="{translate key="plugins.generic.codecheck.badge.textOnly"}"
						/>

						<label class="pkp_form_label" for="codecheckBadgeTextColor">{translate key="plugins.generic.codecheck.settings.badge.textColor.label"}</label>
						<label class="description">{translate key="plugins.generic.codecheck.settings.badge.textColor.description"}</label>
						<div class="badge-color">
							<input
								type="color"
								id="codecheckBadgeTextColor"
								name="codecheckBadgeTextColor"
								value="{$codecheckBadgeTextColor|escape}"
							/>
							<span class="badge-color-value">{$codecheckBadgeTextColor|escape}</span>
						</div>
					</div>
				</div>

				<div id="customBadgeUrlSection" class="badge-dependent-field"{if $codecheckBadgeType != 'custom'} style="display:none"{/if}>
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.custom.url.label"}</label>
					<input
						type="url"
						name="codecheckBadgeCustomUrl"
						class="pkpFormField__input"
						value="{$codecheckBadgeCustomUrl|escape}"
						placeholder="https://example.com/your-badge.png"
					/>
				</div>
			{/fbvFormSection}

			{* Where clicking the badge takes a reader *}
			{fbvFormSection list=true}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.linkTarget.label"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.badge.linkTarget.description"}</label>
				{fbvElement
					type="select"
					id="codecheckBadgeLinkTarget"
					class="codecheck-form-select"
					from=$codecheckBadgeLinkTargets
					selected=$codecheckBadgeLinkTarget
					translate=false
				}
			{/fbvFormSection}

			{fbvFormSection list=true}
				<div class="field-header">
					<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.height.label"}</label>
				</div>
				<label class="description">{translate key="plugins.generic.codecheck.settings.badge.height.description"}</label>
				<div class="badge-height">
					<input
						type="number"
						name="codecheckBadgeHeight"
						class="pkpFormField__input"
						value="{$codecheckBadgeHeight|escape}"
						min="10"
						max="200"
					/>
					<span class="badge-height-unit">px</span>
				</div>
			{/fbvFormSection}
		{/fbvFormSection}

		{* Clear / Reset CODECHECK Metadata DB *}
		{fbvFormSection
			list=true
		}
			<div class="field-header">
				<label class="pkp_form_label">Clear / Reset CODECHECK Metadata Database</label>
			</div>
			<button
				type="button"
				id="resetSchema"
				class="pkpButton btn-remove"
				data-url="{url router=$smarty.const.ROUTE_COMPONENT component='grid.settings.plugins.SettingsPluginGridHandler' op='manage' category='generic' plugin=$pluginName verb='resetSchema' save=true}"
			>
				Clear / Reset DB
			</button>
		{/fbvFormSection}

		{* TODO: Add more settings in future development *}
		{* - ORCID integration settings *}
		{* - Email template settings *}
		
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
