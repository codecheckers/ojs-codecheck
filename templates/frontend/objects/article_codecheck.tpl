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
        <div class="sub_item" style="display: block;">
            <img src="{$logoUrl|escape}" alt="CODECHECK" style="height: 18px; margin-right: 3px;">
            <h2 class="label">Codecheckers</h2>
            <span>{$codecheckerNames|escape}</span>
        </div>
        <div class="sub_item">
            <h2 class="label">Certificate</h2>
            
            {if $certificateLink}
                <div class="value">
                    <a href="{$certificateLink|escape}" 
                    target="_blank"
                    title="{translate key='plugins.generic.codecheck.certificate.landingPage.title'}">
                        {translate key='plugins.generic.codecheck.certificate.prefix'} {$codecheckData->getCertificate()|escape}
                    </a>
                </div>
            {/if}
            
            {if $doiLink}
                <div class="value">
                    <a href="{$doiLink|escape}" 
                       target="_blank"
                       title="{translate key='plugins.generic.codecheck.certificate.document.title'}">
                        {$doiLink|escape}
                    </a>
                </div>
            {/if}
        </div>
    </div>

{elseif $codecheckStatus == 'pending'}
    <div class="item codecheck-pending" style="background: #fff8e1; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0;">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <img src="{$logoUrl|escape}" alt="CODECHECK" style="height: 24px; margin-right: 8px;">
            <span style="background: #ffc107; color: #212529; padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold;">
                ⏳ {translate key='plugins.generic.codecheck.status.inProgress'}
            </span>
        </div>
        
        <h4 style="color: #bf8f00; margin: 0 0 10px 0; font-size: 1.1em; font-weight: bold;">
            CODECHECK
        </h4>
        
        <p style="margin: 0 0 10px 0; font-size: 0.85em; color: #666; line-height: 1.4;">
            {translate key='plugins.generic.codecheck.status.verificationInProgress'}
        </p>
        
        {if $codeRepo || $dataRepo}
            <div style="font-size: 0.8em; color: #666;">
                {if $codeRepo}
                    <p style="margin: 2px 0; word-break: break-all;">
                        <strong>{translate key='plugins.generic.codecheck.codeRepository'}:</strong> 
                        <a href="{$codeRepo|escape}" target="_blank" style="color: #bf8f00;">
                            {$codeRepo|truncate:30|escape}
                        </a>
                    </p>
                {/if}
                {if $dataRepo}
                    <p style="margin: 2px 0; word-break: break-all;">
                        <strong>{translate key='plugins.generic.codecheck.dataRepository'}:</strong> 
                        <a href="{$dataRepo|escape}" target="_blank" style="color: #bf8f00;">
                            {$dataRepo|truncate:30|escape}
                        </a>
                    </p>
                {/if}
            </div>
        {/if}
    </div>
{/if}