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

		$('#testOrcidSetup').on('click', function () {
			const $btn    = $(this);
			const $result = $('#orcidTestResult');

			const labelTesting  = $btn.data('label-testing');
			const labelDefault  = $btn.data('label-default');
			const labelFallback = $btn.data('label-fallback');

			$btn.prop('disabled', true).text(labelTesting);
			$result.hide().removeClass('orcid-test--success orcid-test--error');

			fetch(pkp.context.apiBaseUrl.replace('/api/v1', '') + '/api/v1/codecheck/orcid-test', {
				headers: { 'X-Csrf-Token': pkp.currentUser.csrfToken }
			})
			.then(r => r.json())
			.then(data => {
				if (data.success) {
					$result
						.addClass('orcid-test--success')
						.text('✓ ' + data.message)
						.show();
				} else {
					$result
						.addClass('orcid-test--error')
						.text('✗ ' + data.error)
						.show();
				}
			})
			.catch((err) => {
				$result
					.addClass('orcid-test--error')
					.text('✗ ' + (err.message || labelFallback))
					.show();
			})
			.finally(() => {
				$btn.prop('disabled', false).text(labelDefault);
			});
		});
	});
	
	function toggleCustomBadgeUrl() {
		var selected = document.querySelector('input[name="codecheckBadgeType"]:checked');
		var section = document.getElementById('customBadgeUrlSection');
		section.style.display = (selected && selected.value === 'custom') ? 'block' : 'none';
	}

	$('.settings-droptown.dropdown').on('mouseenter', function() {
		const $dropdown = $(this);
		const $content = $dropdown.find('.dropdown-content');
		const rect = this.getBoundingClientRect();
		const contentHeight = $content.outerHeight() || 200;
		const spaceBelow = window.innerHeight - rect.bottom;

		if (spaceBelow < contentHeight) {
			$dropdown.addClass('dropdown-up');
		} else {
			$dropdown.removeClass('dropdown-up');
		}
	});
</script>
{/literal}

<style>
#orcidTestResult {
	margin-top: 0.75rem;
	padding: 0.5rem 0.75rem;
	border-radius: 4px;
	font-size: 0.875rem;
	display: none;
}
.orcid-test--success {
	background: #d4edda;
	color: #155724;
	border: 1px solid #c3e6cb;
}
.orcid-test--error {
	background: #f8d7da;
	color: #721c24;
	border: 1px solid #f5c6cb;
}
</style>

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
		{fbvFormSection list=true}
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
		{/fbvFormSection}

		{* Clear / Reset CODECHECK Metadata DB *}
		{fbvFormSection list=true}
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
				<fieldset>
					<div class="settings-droptown dropdown">
						<button type="button" class="dropbtn">{translate key="plugins.generic.codecheck.settings.status.selectStatuses"} ⚙</button>
						<div class="dropdown-content">
							{foreach from=$codecheckStatuses item=statusKey}
								<div class="dropdown-checkbox-input">
									<input
										type="checkbox"
										name="codecheckStatusKeysSelected[]"
										id="status-{$statusKey}"
										value="{$statusKey|escape}"
										{if $codecheckStatusKeysSelected && in_array($statusKey, $codecheckStatusKeysSelected)}checked{/if}
									/>
									<label for="status-{$statusKey}">
										{translate key=$statusKey}
									</label>
								</div>
							{/foreach}
						</div>
					</div>
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

		{* ------------------------------------------------------------------ *}
		{* ORCID Deposition Settings                                           *}
		{* ------------------------------------------------------------------ *}
		{fbvFormSection id="settingsHeader" list=true}
			<h3 class="section-title">{translate key="plugins.generic.codecheck.orcid.settingsTitle"}</h3>
			<p class="section-description">{translate key="plugins.generic.codecheck.orcid.settingsDescription"}</p>
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.enable"}</label>
			</div>
			{fbvElement
				type="checkbox"
				id="orcidEnabled"
				checked=$orcidEnabled
				label="plugins.generic.codecheck.orcid.enable"
			}
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.apiType"}</label>
			</div>
			{fbvElement
				type="select"
				id="orcidApiType"
				class="codecheck-form-select"
				from=$orcidApiTypes
				selected=$orcidApiType
				translate=false
			}
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.clientId"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.orcid.clientIdDescription"}</label>
			<input
				type="text"
				name="orcidClientId"
				class="pkpFormField__input"
				value="{$orcidClientId|escape}"
				placeholder="APP-XXXXXXXXXXXX"
			/>
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.clientSecret"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.orcid.clientSecretDescription"}</label>
			<input
				type="password"
				name="orcidClientSecret"
				class="pkpFormField__input"
				value=""
				placeholder="{if $orcidClientSecret}••••••••••••{/if}"
			/>
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.city"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.orcid.cityDescription"}</label>
			<input
				type="text"
				name="orcidCity"
				class="pkpFormField__input"
				value="{$orcidCity|escape}"
				placeholder="e.g. Amsterdam"
			/>
		{/fbvFormSection}

		{fbvFormSection list=true}
			<div class="field-header">
				<label class="pkp_form_label">{translate key="plugins.generic.codecheck.orcid.test.title"}</label>
			</div>
			<label class="description">{translate key="plugins.generic.codecheck.orcid.test.description"}</label>
			<button
				type="button"
				id="testOrcidSetup"
				class="pkpButton btn-add"
				style="margin-top: 0;"
				data-label-default="{translate key="plugins.generic.codecheck.orcid.test.button"}"
				data-label-testing="{translate key="plugins.generic.codecheck.orcid.test.button.testing"}"
				data-label-fallback="{translate key="plugins.generic.codecheck.orcid.test.error.requestFailed"}"
			>
				{translate key="plugins.generic.codecheck.orcid.test.button"}
			</button>
			<div id="orcidTestResult"></div>
		{/fbvFormSection}

	{/fbvFormArea}

	{* Badge / Logo setting *}
	{fbvFormSection list=true}
		<div class="field-header">
			<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.title"}</label>
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
		</div>

		<div id="customBadgeUrlSection" style="{if $codecheckBadgeType == 'custom'}display:block{else}display:none{/if}; margin-top:0.75rem;">
			<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.custom.url.label"}</label>
			<input
				type="url"
				name="codecheckBadgeCustomUrl"
				class="pkpFormField__input"
				value="{$codecheckBadgeCustomUrl|escape}"
				placeholder="https://example.com/your-badge.png"
			/>
		</div>
		
		<div style="margin-top:0.75rem;">
			<label class="pkp_form_label">{translate key="plugins.generic.codecheck.settings.badge.height.label"}</label>
			<p class="description">{translate key="plugins.generic.codecheck.settings.badge.height.description"}</p>
			<input
				type="number"
				name="codecheckBadgeHeight"
				class="pkpFormField__input"
				value="{$codecheckBadgeHeight|escape}"
				min="10"
				max="200"
				style="width:100px;"
			/>
			<span style="font-size:13px; color:#666; margin-left:6px;">px</span>
		</div>
	{/fbvFormSection}
	{fbvFormButtons submitText="common.save"}
</form>
