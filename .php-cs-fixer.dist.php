<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@auto'       => true,
        '@PhpCsFixer' => true,

        // Every file declares strict types. This is the only risky rule enabled; the
        // rule sets above are the non-risky ones, so allowing risky rules adds nothing else.
        'declare_strict_types' => true,

        // Opening braces on next line for classes and functions (Allman style).
        // Closures and control structures keep end-of-line brace.
        'braces_position' => [
            'classes_opening_brace'             => 'next_line_unless_newline_at_signature_end',
            'functions_opening_brace'           => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace'   => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'control_structures_opening_brace'  => 'same_line',
        ],

        // Preserve 'else if' as two keywords; do not merge to 'elseif'.
        'elseif' => false,

        // Space around '.' concatenation operator.
        'concat_space' => ['spacing' => 'one'],

        // No blank line between <?php and declare(strict_types=1).
        'blank_line_after_opening_tag' => false,
        'linebreak_after_opening_tag'  => true,

        // Short array syntax [].
        'array_syntax' => ['syntax' => 'short'],

        // Trailing commas in multiline arrays, parameters, arguments, and match arms.
        'trailing_comma_in_multiline' => [
            'elements'      => ['arguments', 'arrays', 'match', 'parameters'],
            'after_heredoc' => false,
        ],

        // No spaces around | and & in union/intersection types: string|int not string | int.
        'types_spaces' => ['space' => 'none'],

        // PHPDoc types: null always last (string|null not null|string).
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm'  => 'none',
        ],

        // Vertical alignment of PHPDoc tags.
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags'  => ['param', 'property', 'property-read', 'property-write', 'return', 'throws', 'type', 'var', 'method'],
        ],

        // Preserve intentional @param/@var PHPDoc even when types match the signature.
        'no_superfluous_phpdoc_tags' => false,

        // Sort use statements alphabetically.
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],

        // One blank line around methods, properties, constants, and cases.
        // Trait imports use 'one' (symmetric); in practice they appear first in the class
        // body so no blank line above is the natural outcome.
        'class_attributes_separation' => [
            'elements' => [
                'method'       => 'one',
                'property'     => 'one',
                'const'        => 'one',
                'case'         => 'one',
                'trait_import' => 'one',
            ],
        ],

        // Keep empty class/method bodies on multiple lines; do not collapse to one-liners.
        'single_line_empty_body' => false,

        // Do not convert Type|null unions to ?Type nullable shorthand (or vice versa).
        'nullable_type_declaration' => false,

        // Lowercase true, false, null.
        'constant_case' => ['case' => 'lower'],

        // Space after cast: (int) $foo.
        'cast_spaces' => ['space' => 'single'],

        // Prevent extra blank lines accumulating.
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
                'use_trait',
            ],
        ],

        // Preserve alignment padding in grouped assignments ($foo   = 1; $fooBar = 2;).
        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal',
        ],

        // Import global-namespace classes (Stringable, Countable, etc.) rather than using
        // a \ prefix, in code and in PHPDoc. Functions and constants are left as written.
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => null,
            'import_functions' => null,
        ],

        // Keep /** @var ... */ inline PHPDoc; do not convert to single-line // comments.
        'phpdoc_to_comment' => false,

        // Keep parameter-level attributes on the same line as their parameter.
        'method_argument_space' => [
            'on_multiline'        => 'ensure_fully_multiline',
            'attribute_placement' => 'same_line',
        ],

        // Disable yoda style null check.
        'yoda_style' => false,

        // Reorder class members.
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public_static',
                'property_protected_static',
                'property_private_static',
                'method_public_static',
                'method_protected_static',
                'method_private_static',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],

        // Do not add trailing periods to PHPDoc summary lines (breaks separator lines like ------).
        'phpdoc_summary' => false,

        // Do not remove placeholder // comments from empty class bodies.
        'no_empty_comment' => false,

        // Preserve alignment padding between type hint and variable name in parameters.
        // Both rules normalise to a single space; disabling preserves PHPStorm alignment.
        'function_typehint_space' => false,
        'type_declaration_spaces' => false,

        // Do not insert blank lines before statements (return, continue, throw, etc.).
        // PHPStorm is configured with blank_lines_before_return_statement = 0.
        'blank_line_before_statement' => false,

        // Do not add @internal to test classes or @coversNothing to test classes without covers.
        'php_unit_internal_class'             => false,
        'php_unit_test_class_requires_covers' => false,

        // Preserve space after ! operator (! $var style from PHPStorm config).
        'unary_operator_spaces' => false,

        // Do not collapse $var = expr; return $var; — the intermediate variable
        // is often needed to carry inline @var type annotations.
        'return_assignment' => false,

        // Do not require explicit public visibility on asymmetric-visibility properties.
        'modifier_keywords' => false,
    ])
    ->setFinder(
        new Finder()
            ->in(__DIR__)
            ->exclude(['vendor']),
    )
;
