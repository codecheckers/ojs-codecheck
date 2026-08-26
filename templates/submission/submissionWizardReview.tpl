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
            {* Repositories and the manifest are held in codecheck_metadata and
               rendered client-side by CodecheckReviewRefresher, which reads the
               CODECHECK API. Only publication data can be shown from here. *}

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