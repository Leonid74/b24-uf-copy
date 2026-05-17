<?php declare(strict_types=1);
/**
 * PHP Coding Standards Fixer.
 *
 * @see https://cs.symfony.com/doc/rules/
 * @see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/rules/index.rst
 * @see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    // ->setIndent("\t")
    ->setLineEnding("\n")
    ->setRules([
        '@PhpCsFixer'       => true,
        '@PhpCsFixer:risky' => true,
        // '@PSR2' => true,
        // '@PSR12' => true,
        // '@Symfony' => true,
        // 'psr0' => true,
        // 'psr4' => true,
        'align_multiline_comment'                  => ['comment_type' => 'phpdocs_only'],
        'array_push'                               => true,
        'array_syntax'                             => ['syntax' => 'short'], // Req. PHP >= 5.4.
        'assign_null_coalescing_to_coalesce_equal' => true, // Req. PHP >= 7.4.
        'backtick_to_shell_exec'                   => true,
        'binary_operator_spaces'                   => [
            'operators' => [
                '|'   => 'single_space',
                '||'  => 'align_single_space_minimal',
                '&&'  => 'align_single_space_minimal',
                '='   => 'align_single_space_minimal',
                '+='  => 'align_single_space_minimal',
                '-='  => 'align_single_space_minimal',
                '.='  => 'align_single_space_minimal',
                '=>'  => 'align_single_space_minimal',
                '??'  => 'align_single_space_minimal',
                '??=' => 'align_single_space_minimal',
                '=='  => 'align_single_space_minimal',
                '===' => 'align_single_space_minimal',
                '!='  => 'align_single_space_minimal',
                '!==' => 'align_single_space_minimal',
            ],
        ],
        'blank_line_after_namespace'       => true,
        'blank_line_after_opening_tag'     => false,
        'blank_lines_before_namespace'     => true,
        'blank_line_before_statement'      => true,
        'blank_line_between_import_groups' => true,
        'braces_position'                  => true,
        'cast_spaces'                      => true,
        'class_attributes_separation'      => [
            'elements' => [
                'const'        => 'only_if_meta',
                'method'       => 'one',
                'property'     => 'only_if_meta',
                'trait_import' => 'none',
                'case'         => 'none',
            ],
        ],
        'class_definition'                        => ['single_line' => true],
        'clean_namespace'                         => true, // Req. PHP >= 8.0.
        'combine_consecutive_issets'              => true,
        'combine_consecutive_unsets'              => true,
        'combine_nested_dirname'                  => true,
        'compact_nullable_type_declaration'       => true,
        'concat_space'                            => ['spacing' => 'one'],
        'constant_case'                           => true,
        'control_structure_braces'                => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],
        'declare_equal_normalize'                 => ['space' => 'none'],
        'declare_strict_types'                    => true,
        'dir_constant'                            => true,
        'doctrine_annotation_array_assignment'    => true,
        'doctrine_annotation_braces'              => true,
        'doctrine_annotation_indentation'         => true,
        'doctrine_annotation_spaces'              => true,
        'echo_tag_syntax'                         => ['format' => 'short', 'shorten_simple_statements_only' => true],
        'elseif'                                  => true,
        'encoding'                                => true,
        'ereg_to_preg'                            => true,
        'explicit_indirect_variable'              => false, // I feel it makes the code actually harder to read
        'explicit_string_variable'                => false, // I feel it makes the code actually harder to read
        'final_internal_class'                    => false,
        'full_opening_tag'                        => true,
        'fully_qualified_strict_types'            => [
            'import_symbols'                        => true,
            'leading_backslash_in_global_namespace' => false,
            'phpdoc_tags'                           => [
                // 'param',
                'phpstan-param',
                'phpstan-property',
                'phpstan-property-read',
                'phpstan-property-write',
                'phpstan-return',
                'phpstan-var',
                'property',
                'property-read',
                'property-write',
                // 'return',
                'see',
                'throws',
                // 'var',
            ],
        ],
        'global_namespace_import'                => [
            'import_classes'   => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'function_declaration'                   => true,
        'function_to_constant'                   => true,
        'general_phpdoc_annotation_remove'       => false, // No use for that
        'header_comment'                         => false,
        'heredoc_indentation'                    => ['indentation' => 'start_plus_one'], // Req. PHP >= 7.3.
        'heredoc_to_nowdoc'                      => false, // Not sure about this one
        'include'                                => true,
        'increment_style'                        => true,
        'indentation_type'                       => true,
        'is_null'                                => false,
        'line_ending'                            => true,
        'linebreak_after_opening_tag'            => false,
        'list_syntax'                            => ['syntax' => 'short'], // Req. PHP >= 7.1.
        'logical_operators'                      => true,
        'lowercase_cast'                         => true,
        'lowercase_keywords'                     => true,
        'magic_constant_casing'                  => true,
        'magic_method_casing'                    => true,
        'mb_str_functions'                       => false, // No, too dangerous to change that
        'method_argument_space'                  => true, // Req. PHP >= 7.3.
        'method_chaining_indentation'            => true,
        'modernize_strpos'                       => true, // needs PHP 8+ or polyfill
        'modernize_types_casting'                => true,
        'multiline_comment_opening_closing'      => false,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'native_function_casing'                 => true,
        'native_function_invocation'             => false, // micro-optimization that makes code uglier without real benefit
        'no_alias_functions'                 => ['sets' => ['@all']],
        'no_alternative_syntax'              => ['fix_non_monolithic_code' => true],
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc'        => true,
        'no_break_comment'                   => true,
        'no_closing_tag'                     => true,
        'no_empty_comment'                   => true,
        'no_empty_phpdoc'                    => true,
        'no_empty_statement'                 => true,
        'no_extra_blank_lines'               => [
            'tokens' => [
                'attribute',
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
                'use',
            ],
        ],
        'no_homoglyph_names'                          => true,
        'no_leading_import_slash'                     => true,
        'no_leading_namespace_whitespace'             => true,
        'no_mixed_echo_print'                         => true,
        'no_multiple_statements_per_line'             => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_null_property_initialization'             => false,
        'no_php4_constructor'                         => true,
        'no_short_bool_cast'                          => true,
        'no_singleline_whitespace_before_semicolons'  => true,
        'no_spaces_after_function_name'               => true,
        'no_spaces_around_offset'                     => true,
        'no_superfluous_elseif'                       => false, // Might be risky on a huge code base
        'no_superfluous_phpdoc_tags'                  => false,
        'no_trailing_comma_in_singleline'             => true,
        'no_trailing_whitespace'                      => true,
        'no_trailing_whitespace_in_comment'           => true,
        'no_unneeded_control_parentheses'             => true,
        'no_unneeded_braces'                          => ['namespaces' => true],
        'no_unneeded_final_method'                    => false,
        'no_unreachable_default_argument_value'       => false,
        'no_unset_cast'                               => true, // Req. PHP >= 8.0.
        'no_unused_imports'                           => true,
        'no_useless_else'                             => true,
        'no_useless_return'                           => true,
        'no_whitespace_before_comma_in_array'         => true, // Req. PHP >= 7.3.
        'no_whitespace_in_blank_line'                 => true,
        'non_printable_character'                     => ['use_escape_sequences_in_strings' => true],
        'not_operator_with_space'                     => false, // No i prefer to keep '!' without spaces
        'not_operator_with_successor_space'           => false, // idem
        'normalize_index_brace'                       => true, // Req. PHP >= 7.4.
        'new_with_parentheses'                        => ['anonymous_class' => true, 'named_class' => true],
        'object_operator_without_whitespace'          => true,
        'octal_notation'                              => true, // Req. PHP >= 8.1.
        'ordered_class_elements'                      => true,
        'ordered_imports'                             => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => [
                'const', 'class', 'function',
            ],
        ],
        'php_unit_construct'                            => true,
        'php_unit_dedicate_assert'                      => ['target' => 'newest'],
        'php_unit_dedicate_assert_internal_type'        => ['target' => 'newest'],
        'php_unit_expectation'                          => ['target' => 'newest'],
        'php_unit_fqcn_annotation'                      => true,
        'php_unit_mock'                                 => ['target' => 'newest'],
        'php_unit_mock_short_will_return'               => true,
        'php_unit_namespaced'                           => true,
        'php_unit_no_expectation_annotation'            => ['target' => 'newest'],
        'php_unit_strict'                               => false, // We sometime actually need assertEquals
        'php_unit_test_annotation'                      => ['style' => 'prefix'],
        'php_unit_test_class_requires_covers'           => false, // We don't care as much as we should about coverage
        'php_unit_test_case_static_method_calls'        => ['call_type' => 'self'],
        'phpdoc_add_missing_param_annotation'           => ['only_untyped' => false],
        'phpdoc_align'                                  => ['align' => 'vertical'],
        'phpdoc_annotation_without_dot'                 => true,
        'phpdoc_indent'                                 => true,
        'phpdoc_inline_tag_normalizer'                  => true,
        'phpdoc_line_span'                              => ['const' => 'multi', 'method' => 'multi', 'property' => 'multi'],
        'phpdoc_no_access'                              => false,
        'phpdoc_no_alias_tag'                           => false,
        'phpdoc_no_empty_return'                        => false,
        'phpdoc_no_package'                             => false,
        'phpdoc_no_useless_inheritdoc'                  => false,
        'phpdoc_param_order'                            => true,
        'phpdoc_order'                                  => ['order' => ['param', 'custom', 'return', 'throws']],
        'phpdoc_return_self_reference'                  => true,
        'phpdoc_scalar'                                 => true,
        'phpdoc_separation'                             => true,
        'phpdoc_single_line_var_spacing'                => true,
        'phpdoc_summary'                                => false,
        'phpdoc_to_comment'                             => false,
        'phpdoc_trim'                                   => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types'                                  => true,
        'phpdoc_types_order'                            => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'alpha'],
        'phpdoc_var_annotation_correct_order'           => true,
        'phpdoc_var_without_name'                       => true,
        'pow_to_exponentiation'                         => false,
        'protected_to_private'                          => true,
        'random_api_migration'                          => false, // This breaks our unit tests
        'return_assignment'                             => true,
        'return_type_declaration'                       => true,
        'self_accessor'                                 => true,
        'semicolon_after_instruction'                   => true,
        'short_scalar_cast'                             => true, // Req. PHP >= 7.4.
        'simple_to_complex_string_variable'             => true, // Req. PHP >= 8.2.
        'simplified_null_return'                        => false, // Prefer to be explicit when returning null
        'single_blank_line_at_eof'                      => true,
        'single_class_element_per_statement'            => true,
        'single_import_per_statement'                   => true,
        'single_line_after_imports'                     => true,
        'single_line_comment_style'                     => true,
        'single_quote'                                  => true,
        'single_space_around_construct'                 => true,
        'single_trait_insert_per_statement'             => true,
        'space_after_semicolon'                         => true,
        'spaces_inside_parentheses'                     => ['space' => 'none'],
        'standardize_not_equals'                        => true,
        'statement_indentation'                         => ['stick_comment_to_next_continuous_control_statement' => false],
        'static_lambda'                                 => false, // Risky if we can't guarantee nobody use `bindTo()`
        'strict_comparison'                             => false, // No, too dangerous to change that
        'strict_param'                                  => false, // No, too dangerous to change that
        'string_implicit_backslashes'                   => [
            'double_quoted' => 'escape',
            'heredoc'       => 'escape',
            'single_quoted' => 'ignore',
        ],
        'switch_case_semicolon_to_colon'  => true,
        'switch_case_space'               => true,
        'ternary_operator_spaces'         => true,
        'ternary_to_null_coalescing'      => true, // Req. PHP >= 7.0.
        'trailing_comma_in_multiline'     => true, // Req. PHP >= 7.3.
        'trim_array_spaces'               => true,
        'type_declaration_spaces'         => ['elements' => ['function', 'property']],
        'types_spaces'                    => ['space' => 'single', 'space_multiple_catch' => 'single'],
        'unary_operator_spaces'           => true,
        'visibility_required'             => ['elements' => ['property', 'method', 'const']], // Req. PHP >= 7.1.
        'void_return'                     => true,
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        'yoda_style'                      => false,
    ])
    ->setFinder(
        (new Finder())
            ->ignoreDotFiles(true)
            ->ignoreVCSIgnored(true)
            ->exclude([
                '.git',
                '.svn',
                '.history',
                '.vscode',
                '.dev_files',
                '.DS_Store',
                'CVS',
                'vendor',
                'logs',
                'TESTS',
                'assets',
                'css',
                'js',
                'bootstrap',
                'database',
                'node_modules',
                'bower_components',
                'storage',
                'img',
                'images',
                'docs',
                'fonts',
            ])
            ->in(__DIR__)
            ->name('*.php')
    )
;
