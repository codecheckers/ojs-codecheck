<?php

namespace APP\plugins\generic\codecheck\tests\SubmissionUnitTests;

use APP\plugins\generic\codecheck\classes\Submission\AvailabilityStatementField;
use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\tests\PKPTestCase;

/**
 * A form that carries a publication, the way PKPMetadataForm does.
 *
 * PKPMetadataForm itself cannot be built here: its constructor reaches for
 * context settings and the submission's locales. What matters to the code under
 * test is the id and the publication, both of which are plain public
 * properties.
 */
class FormWithPublication extends FormComponent
{
    public $publication;

    public function __construct(string $id, $publication = null)
    {
        parent::__construct($id, 'PUT', '', ['en']);
        $this->publication = $publication;
    }
}

/**
 * The availability statement on the publication metadata form.
 *
 * Before this the statement could only be written by the author in the
 * submission wizard or through the REST API, while being rendered on every
 * article landing page — so an empty or wrong statement could not be corrected
 * by anyone (Issue #167).
 *
 * What is tested here is which form the field lands on and where its value
 * comes from. That it saves is not the plugin's code: the metadata form is a
 * PUT against the publication API and `Schema` puts the field on the
 * publication schema, so the round trip belongs to OJS.
 */
class AvailabilityStatementFieldUnitTest extends PKPTestCase
{
    private function publicationWith(?string $statement): Publication
    {
        $publication = $this->createMock(Publication::class);
        $publication->method('getData')->with('dataAvailabilityStatement')->willReturn($statement);

        return $publication;
    }

    private function addTo(FormComponent $form): bool
    {
        return (new AvailabilityStatementField())->addToMetadataForm('Form::config::before', $form);
    }

    public function testTheFieldIsAddedToThePublicationMetadataForm()
    {
        $form = new FormWithPublication(PKPMetadataForm::FORM_METADATA, $this->publicationWith(null));

        $this->addTo($form);

        $this->assertCount(1, $form->fields);
        $this->assertSame('dataAvailabilityStatement', $form->fields[0]->name);
    }

    public function testTheFieldShowsWhatIsAlreadyRecorded()
    {
        // An editor correcting a statement has to see the one that is there;
        // a field that started empty would quietly wipe it on the next save.
        $form = new FormWithPublication(
            PKPMetadataForm::FORM_METADATA,
            $this->publicationWith('Data at https://doi.org/10.5281/zenodo.1234567')
        );

        $this->addTo($form);

        $this->assertSame('Data at https://doi.org/10.5281/zenodo.1234567', $form->fields[0]->value);
    }

    public function testTheFieldIsPlainTextRatherThanRichText()
    {
        // The article page renders the statement through strip_unsafe_html|nl2br
        // and the wizard offers a plain textarea, so rich text would only admit
        // markup that is stripped again on the way out.
        $form = new FormWithPublication(PKPMetadataForm::FORM_METADATA, $this->publicationWith(null));

        $this->addTo($form);

        $this->assertInstanceOf(FieldTextarea::class, $form->fields[0]);
        $this->assertNotInstanceOf(FieldRichTextarea::class, $form->fields[0]);
    }

    public function testTheWizardsForTheEditorsStepIsLeftAlone()
    {
        // ForTheEditors extends PKPMetadataForm, so an instanceof check would
        // add the field to the wizard as well — twice over, beside the
        // plugin's own availability field.
        $form = new FormWithPublication('forTheEditors', $this->publicationWith(null));

        $this->addTo($form);

        $this->assertSame([], $form->fields);
    }

    public function testOtherFormsAreLeftAlone()
    {
        foreach (['submissionStart', 'titleAbstract', 'citations', 'issueEntry'] as $id) {
            $form = new FormWithPublication($id, $this->publicationWith(null));

            $this->addTo($form);

            $this->assertSame([], $form->fields, "the field was added to the {$id} form");
        }
    }

    public function testTheHookAlwaysReturnsFalse()
    {
        // Returning true would stop OJS and every other plugin from configuring
        // the form.
        $this->assertFalse($this->addTo(new FormWithPublication(PKPMetadataForm::FORM_METADATA, $this->publicationWith(null))));
        $this->assertFalse($this->addTo(new FormWithPublication('titleAbstract', $this->publicationWith(null))));
    }
}
