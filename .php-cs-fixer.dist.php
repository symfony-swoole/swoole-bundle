<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__])
    ->exclude([
        '.php-cs-fixer.dist.php',
        'tests/Fixtures/Symfony/app/var',
        'tests/Unit/Server/Php8',
        'vendor',
        'ext',
    ]);

$config = new PhpCsFixer\Config();
/**
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer for rules
 */
return $config->setRules([
        '@PER-CS2.0' => true,
        '@PHP82Migration' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => true,
        'braces_position' => ['classes_opening_brace' => 'next_line_unless_newline_at_signature_end'],
        'class_reference_name_casing' => false,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_type_declaration' => true,
        'global_namespace_import' => [
            'import_classes' => true,
        ],
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'native_constant_invocation' => [
            'fix_built_in' => false,
            'scope' => 'all',
        ],
        'native_function_invocation' => [
            'include' => [],
            'exclude' => ['@all'],
        ],
        'no_blank_lines_after_class_opening' => true,
        'no_null_property_initialization' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'nullable_type_declaration_for_default_null_value' => false,
        'ordered_class_elements' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'no_unused_imports' => true,
        'phpdoc_order' => [
            'order' => ['param', 'return', 'throws'],
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
        ],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
