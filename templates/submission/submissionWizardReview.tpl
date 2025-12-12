{**
 * templates/submission/submissionWizardReview.tpl
 * CODECHECK review section
 *}

{if $submission->getData('codecheckOptIn')}
    {assign var="publication" value=$submission->getCurrentPublication()}
    
    <div class="submissionWizard__reviewPanel">
        <div class="submissionWizard__reviewPanel__header">
            <h3>{translate key="plugins.generic.codecheck.review.title"}</h3>
        </div>
        <div class="submissionWizard__reviewPanel__body">
            {if $publication->getData('codeRepository')}
                <div class="submissionWizard__reviewPanel__item">
                    <h4>{translate key="plugins.generic.codecheck.codeRepository"}</h4>
                    <div class="review-value">
                        <p>{$publication->getData('codeRepository')|escape|nl2br}</p>
                    </div>
                </div>
            {/if}

            {if $publication->getData('dataRepository')}
                <div class="submissionWizard__reviewPanel__item">
                    <h4>{translate key="plugins.generic.codecheck.dataRepository"}</h4>
                    <div class="review-value">
                        <p>{$publication->getData('dataRepository')|escape|nl2br}</p>
                    </div>
                </div>
            {/if}

            {if $publication->getData('manifestFiles')}
                <div class="submissionWizard__reviewPanel__item">
                    <h4>{translate key="plugins.generic.codecheck.manifestFiles.label"}</h4>
                    <div class="review-value">
                        <pre>{$publication->getData('manifestFiles')|escape}</pre>
                    </div>
                </div>
            {/if}

            {if $publication->getData('dataAvailabilityStatement')}
                <div class="submissionWizard__reviewPanel__item">
                    <h4>{translate key="plugins.generic.codecheck.dataAvailability"}</h4>
                    <div class="review-value">
                        {$publication->getData('dataAvailabilityStatement')|strip_unsafe_html}
                    </div>
                </div>
            {/if}
        </div>
    </div>
{else}
    <div class="submissionWizard__reviewPanel">
        <div class="submissionWizard__reviewPanel__header">
            <h3>{translate key="plugins.generic.codecheck.review.title"}</h3>
        </div>
        <div class="submissionWizard__reviewPanel__body">
            <div class="submissionWizard__reviewPanel__item">
                <p class="description">
                    <em>{translate key="plugins.generic.codecheck.notOptedIn"}</em>
                </p>
            </div>
        </div>
    </div>
{/if}