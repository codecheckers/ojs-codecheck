<?php

namespace APP\plugins\generic\codecheck\classes\Submission;

class Schema
{
    /**
     * Add CODECHECK fields to publication schema
     */
    public function addToSchemaPublication(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        // Repositories and the manifest live in codecheck_metadata, written by
        // the wizard and edited by the codechecker in one place. Only the
        // availability statement is publication metadata: it has no counterpart
        // in codecheck.yml and no codechecker view.
        $fields = [
            'dataAvailabilityStatement' => 'string',
        ];

        foreach ($fields as $fieldName => $type) {
            $schema->properties->{$fieldName} = (object)[
                'type' => $type,
                'multilingual' => false,
                'apiSummary' => true,
                'validation' => ['nullable']
            ];
        }

        return false;
    }
}