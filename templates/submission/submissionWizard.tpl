{**
 * templates/submission/submissionWizard.tpl
 * CODECHECK submission wizard fields
 *}

{if $submission->getData('codecheckOptIn')}
    <div id="codecheck-submission-fields" class="codecheck-wizard-section">
        <p class="description">
            {translate key="plugins.generic.codecheck.submission.description"}
        </p>

        <div class="pkpFormField">
            <div class="pkpFormField__heading">
                <label for="repositories">
                    {translate key="plugins.generic.codecheck.repositories.label"}
                </label>
            </div>
            <div class="pkpFormField__description">
                {translate key="plugins.generic.codecheck.repositories.label.description"}
            </div>
            <textarea 
                id="repositories" 
                name="repositories" 
                class="pkpFormField__input pkpFormField--textarea"
                rows="3"
            ></textarea>
        </div>

        <div class="pkpFormField">
            <div class="pkpFormField__heading">
                <label for="manifestFiles">
                    {translate key="plugins.generic.codecheck.manifestFiles.label"}
                </label>
            </div>
            <div class="pkpFormField__description">
                {translate key="plugins.generic.codecheck.manifestFiles.description"}
            </div>
            <textarea 
                id="manifestFiles" 
                name="manifestFiles" 
                class="pkpFormField__input pkpFormField--textarea"
                rows="6"
            ></textarea>
        </div>

        <div class="pkpFormField">
            <div class="pkpFormField__heading">
                <label for="dataAvailabilityStatement">
                    {translate key="plugins.generic.codecheck.dataAvailability"}
                </label>
            </div>
            <div class="pkpFormField__description">
                {translate key="plugins.generic.codecheck.dataAvailability.description"}
            </div>
            <textarea 
                id="dataAvailabilityStatement" 
                name="dataAvailabilityStatement" 
                class="pkpFormField__input pkpFormField--textarea"
                rows="8"
                placeholder="{translate key="plugins.generic.codecheck.dataAvailability.placeholder"}"
            ></textarea>
        </div>
    </div>
{else}
    <div class="panelSection__content">
        <p class="description">
            <em>{translate key="plugins.generic.codecheck.notOptedIn"}</em>
        </p>
    </div>
{/if}