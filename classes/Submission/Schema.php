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

        $fields = [
            'codeRepository' => 'string',
            'dataRepository' => 'string',
            'manifestFiles' => 'string',
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