<?php
/**
 * @file classes/Submission/AvailabilityStatementField.php
 *
 * Copyright (c) 2025 CODECHECK Initiative
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AvailabilityStatementField
 * @brief Puts the data and software availability statement on the publication
 *  metadata form, so an editor can correct it after submission.
 */

namespace APP\plugins\generic\codecheck\classes\Submission;

use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPMetadataForm;

class AvailabilityStatementField
{
    /**
     * Add the statement to OJS's own publication Metadata form.
     *
     * Until this existed the field could only be written by the author in the
     * submission wizard or through the REST API: every editorial view of it was
     * read-only, so a statement left empty or entered in the wrong place could
     * not be fixed by anyone — while being rendered on the article landing page.
     *
     * Nothing needs to save it. The metadata form is a PUT against the
     * publication API and `Schema` already puts the field on the publication
     * schema, so it round-trips on its own. That is the opposite of the wizard,
     * which needs `CodecheckPlugin::saveWizardFieldsFromRequest()` precisely
     * because it posts outside that API.
     *
     * The field is offered for every submission, opted in or not, because the
     * statement itself is: the wizard collects it either way and the article
     * page renders it either way. The journal setting decides whether readers
     * see it, not whether it can be recorded.
     *
     * @param FormComponent $form the form being configured
     * @return bool false, so OJS and other plugins still get the hook
     */
    public function addToMetadataForm(string $hookName, FormComponent $form): bool
    {
        // Match the id exactly. `ForTheEditors` — the wizard's "For the
        // Editors" step — extends PKPMetadataForm, so an instanceof check would
        // add the field there too, next to the plugin's own wizard field.
        if ($form->id !== PKPMetadataForm::FORM_METADATA) {
            return false;
        }

        $form->addField(new FieldTextarea('dataAvailabilityStatement', [
            'label' => __('plugins.generic.codecheck.dataAvailability'),
            'description' => __('plugins.generic.codecheck.dataAvailability.editorDescription'),
            // Not a FieldRichTextarea: the article page renders the statement
            // through strip_unsafe_html|nl2br and the wizard offers a plain
            // textarea, so rich text would only admit markup that is stripped
            // again on the way out.
            'value' => $form->publication->getData('dataAvailabilityStatement'),
        ]));

        return false;
    }
}
