<?php

/**
 * Coding standard for the CODECHECK plugin.
 *
 * The rule set is PKP's own — `lib/pkp/.php_cs_rules` in an OJS checkout —
 * so plugin code reads like the core it lives in, rather than merely passing
 * PSR-12. It is copied rather than included: the plugin is developed as a
 * standalone checkout and linting must work without an OJS install beside it.
 * The one rule left out is PKP's custom `PKP/hookfixer`, which is a fixer class
 * that only exists inside lib/pkp.
 *
 * Run it with `make lint` (report only) or `make lint-fix` (rewrite files).
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    // Not ours to format: dependencies, the built bundle, the OJS test dump,
    // and Smarty templates (which are .tpl, but the directory also holds none
    // of our PHP).
    ->exclude(['node_modules', 'public', 'testData', 'templates', 'vendor']);

$config = new PhpCsFixer\Config();

// The tool refuses to run on a PHP version it does not yet claim support for.
// A formatter is not the place to enforce that, and CI runs on whatever PHP the
// runner ships; PKP's own `composer fix` sets the equivalent environment
// variable for the same reason.
$config->setUnsupportedPhpVersionAllowed(true);

return $config
    ->setRules([
        '@PSR12' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'explicit_string_variable' => true,
        'list_syntax' => ['syntax' => 'short'],
        'method_chaining_indentation' => true,
        'no_unused_imports' => true,
        'no_spaces_around_offset' => true,
        'no_superfluous_phpdoc_tags' => true,
        'no_whitespace_before_comma_in_array' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_var_annotation_correct_order' => true,
        'single_quote' => true,
        'standardize_increment' => true,
        'standardize_not_equals' => true,
        'ternary_to_null_coalescing' => true,
    ])
    ->setRiskyAllowed(false)
    ->setFinder($finder);
