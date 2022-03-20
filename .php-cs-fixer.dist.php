<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__])
    ->exclude([
        'tests/Fixtures/Symfony/app/var',
        'tests/Unit/Server/Php8',
        'vendor',
    ]);

$config = new PhpCsFixer\Config();
/**
 * @see https://github.com/FriendsOfPHP/PHP-CS-Fixer for rules
 */
return $config->setRules([
        '@Symfony' => true,
        '@PHP71Migration' => true,
        '@PHP73Migration' => true,
        '@PHP74Migration' => true,
        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'compact_nullable_typehint' => true,
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'no_null_property_initialization' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_order' => true,
        'phpdoc_types_order' => true,
        'ordered_class_elements' => true,
        'array_indentation' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'no_blank_lines_after_class_opening' => true,
        'blank_line_before_statement' => true,
        'class_reference_name_casing' => false,
    ])
    ->setRiskyAllowed(false)
    ->setFinder($finder);
