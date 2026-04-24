{**
 * templates/frontend/objects/article_codecheck.tpl
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display CODECHECK information on the article details page sidebar.
 *}

{if $codecheckStatus == 'completed'}
<div class="item certificate" style="padding: 15px; margin: 5px 0;">

    {* Badge *}
    <div class="sub_item" style="display:flex; align-items:center; margin-bottom:8px;">
        <img src="{$logoUrl|escape}" alt="{translate key='plugins.generic.codecheck.badge.altText'}" style="height:18px; margin-right:6px;">
    </div>

    {* Codecheckers with ORCIDs *}
    <div class="sub_item">
        <h2 class="label">{translate key='plugins.generic.codecheck.codecheckers.title'}</h2>
        {foreach from=$codecheckers item=checker}
            <div class="value">
                {$checker.name|escape}
                {if $checker.orcid}
                    <a href="https://orcid.org/{$checker.orcid|escape}" target="_blank" style="margin-left:4px;">
                        <img src="https://orcid.org/sites/default/files/images/orcid_16x16.png"
                             alt="ORCID" style="height:14px;">
                    </a>
                {/if}
            </div>
        {/foreach}
    </div>

    {* Certificate *}
    <div class="sub_item">
        <h2 class="label">{translate key='plugins.generic.codecheck.certificate.title'}</h2>
        {if $certificateLink}
            <div class="value">
                <a href="{$certificateLink|escape}" target="_blank">
                    {translate key='plugins.generic.codecheck.certificate.prefix'} {$linkText|escape}
                </a>
            </div>
        {/if}
        {if $doiLink}
            <div class="value">
                <a href="{$doiLink|escape}" target="_blank">{$doiLink|escape}</a>
            </div>
        {/if}
    </div>

    {* More details toggle *}
    <div class="sub_item" style="margin-top:10px;">
        <a href="#" class="codecheck-more-toggle"
           onclick="
               var d=document.getElementById('codecheck-details-{$articleId|escape}');
               d.style.display=(d.style.display==='none'?'block':'none');
               this.textContent=(d.style.display==='none'?'▶ {translate key='plugins.generic.codecheck.moreDetails'}':'▼ {translate key='plugins.generic.codecheck.hideDetails'}');
               return false;"
           style="font-size:0.85em; color:#1a6496;">
            ▶ {translate key='plugins.generic.codecheck.moreDetails'}
        </a>
    </div>

    {* Expandable details *}
    <div id="codecheck-details-{$articleId|escape}" style="display:none; margin-top:10px; font-size:0.85em; border-top:1px solid #ddd; padding-top:10px;">

        {if $certificateDate}
            <div class="sub_item">
                <h2 class="label">{translate key='plugins.generic.codecheck.checkDate'}</h2>
                <div class="value">{$certificateDate|escape}</div>
            </div>
        {/if}

        {if $summary}
            <div class="sub_item">
                <h2 class="label">{translate key='plugins.generic.codecheck.certificate.summary'}</h2>
                <div class="value">{$summary|escape}</div>
            </div>
        {/if}

        {if $repository}
            <div class="sub_item">
                <h2 class="label">{translate key='plugins.generic.codecheck.repositories.title'}</h2>
                <div class="value">
                    <a href="{$repository|escape}" target="_blank">{$repository|truncate:40|escape}</a>
                </div>
            </div>
        {/if}

        {if $manifest}
            <div class="sub_item">
                <h2 class="label">{translate key='plugins.generic.codecheck.manifest.title'}</h2>
                <ul class="value" style="margin:4px 0 0 0; padding-left:16px;">
                    {foreach from=$manifest item=file}
                        <li>{$file.file|escape}</li>
                    {/foreach}
                </ul>
            </div>
        {/if}

        {if $additionalContent}
            <div class="sub_item">
                <h2 class="label">{translate key='plugins.generic.codecheck.additionalContent.frontendLabel'}</h2>
                <div class="value">{$additionalContent|escape}</div>
            </div>
        {/if}

    </div>{* end codecheck-details *}

</div>

{elseif $codecheckStatus == 'pending'}
<div class="item codecheck-pending" style="padding: 15px; margin: 5px 0;">

    <div class="sub_item" style="display:flex; align-items:center; margin-bottom:8px;">
        <img src="{$logoUrl|escape}" alt="{translate key='plugins.generic.codecheck.badge.altText'}" style="height:18px; margin-right:6px;">
    </div>

    <div class="sub_item">
        <h2 class="label">{translate key='plugins.generic.codecheck.status.pending'}</h2>
        <div class="value">{translate key='plugins.generic.codecheck.status.verificationInProgress'}</div>
    </div>

    {if $codeRepo}
        <div class="sub_item">
            <h2 class="label">{translate key='plugins.generic.codecheck.codeRepository'}</h2>
            <div class="value">
                <a href="{$codeRepo|escape}" target="_blank">{$codeRepo|truncate:30|escape}</a>
            </div>
        </div>
    {/if}

    {if $dataRepo}
        <div class="sub_item">
            <h2 class="label">{translate key='plugins.generic.codecheck.dataRepository'}</h2>
            <div class="value">
                <a href="{$dataRepo|escape}" target="_blank">{$dataRepo|truncate:30|escape}</a>
            </div>
        </div>
    {/if}

</div>
{/if}