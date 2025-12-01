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
            <label for="codeRepository">
                {translate key="plugins.generic.codecheck.codeRepository"}
            </label>
            <div class="pkpFormField__description">
                {translate key="plugins.generic.codecheck.codeRepository.description"}
            </div>
            <textarea 
                id="codeRepository" 
                name="codeRepository" 
                class="pkpFormField__input pkpFormField--textarea"
                rows="3"
            ></textarea>
        </div>

        <div class="pkpFormField">
            <label for="dataRepository">
                {translate key="plugins.generic.codecheck.dataRepository"}
            </label>
            <div class="pkpFormField__description">
                {translate key="plugins.generic.codecheck.dataRepository.description"}
            </div>
            <textarea 
                id="dataRepository" 
                name="dataRepository" 
                class="pkpFormField__input pkpFormField--textarea"
                rows="3"
            ></textarea>
        </div>

        <div class="pkpFormField">
            <label for="manifestFiles">
                {translate key="plugins.generic.codecheck.manifestFiles.label"}
            </label>
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
            <label for="dataAvailabilityStatement">
                {translate key="plugins.generic.codecheck.dataAvailability"}
            </label>
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