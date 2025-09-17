<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->exclude('vendor')
    ->exclude('tests')
    ->exclude('database')
    ->name('*.php');

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,

        // Array formatting
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // Import formatting
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,

        // Code cleanup
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => false,
        ],

        // Security
        'no_php4_constructor' => true,
        'modernize_types_casting' => true,

        // Spacing
        'concat_space' => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'method_chaining_indentation' => true,

        // Control structures
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // Functions
        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],

        // Classes
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
            ],
        ],
        'visibility_required' => [
            'elements' => ['method', 'property'],
        ],

        // Strict types
        'declare_strict_types' => false, // Keep disabled for compatibility

        // Documentation
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'phpdoc_separation' => true,
        'phpdoc_trim' => true,

        // Code analysis
        'strict_comparison' => false, // Too aggressive for this codebase
        'strict_param' => false,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);