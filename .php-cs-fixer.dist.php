<?php

declare(strict_types=1);

/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.95.27|configurator
 * you can change this configuration by importing this file.
 */

use PhpCsFixer\Config\RuleCustomisationPolicyInterface;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;

// PHP CS Fixer can only tokenize the syntax of the PHP version it runs on, and the scripts directory contains PHP 8.5 code
if (PHP_VERSION_ID < 80500) {
    fprintf(STDERR, "PHP CS Fixer must be run with PHP 8.5 or later (you are using PHP %s)\n", PHP_VERSION);
    exit(1);
}

if (!defined('PhpCsFixer\\Console\\Application::VERSION')) {
    fwrite(STDERR, "Your version of PHP CS Fixer is too old: please upgrade it\n");
    exit(1);
}
if (version_compare(PhpCsFixer\Console\Application::VERSION, '3.95.27') < 0) {
    fprintf(STDERR, "Your version of PHP CS Fixer (%s) is too old: please upgrade it\n", PhpCsFixer\Console\Application::VERSION);
    exit(1);
}

// The files in the scripts directory that must be compatible with PHP 5.5 (paths relative to this directory)
const PHP55_FILES = [];

// The files outside of the scripts directory that require PHP 8.5 or later (paths relative to this directory)
const PHP85_FILES = [
    '.php-cs-fixer.dist.php',
];

// The rules are the ones for PHP 8.5: this policy disables or reconfigures some of them for the files that must be compatible with PHP 5.5
$php55Policy = new class implements RuleCustomisationPolicyInterface {
    /**
     * @var array<non-empty-string, Closure(SplFileInfo): (bool|FixerInterface)>|null
     */
    private ?array $customisers = null;

    /**
     * @var array<string, bool>
     */
    private array $isPHP85Cache = [];

    public function getPolicyVersionForCache(): string
    {
        return hash_file('xxh128', __FILE__);
    }

    public function getRuleCustomisers(): array
    {
        return $this->customisers ??= $this->buildRuleCustomisers();
    }

    /**
     * @return array<non-empty-string, Closure(SplFileInfo): (bool|FixerInterface)>
     */
    private function buildRuleCustomisers(): array
    {
        $customisers = array_fill_keys(
            [
                'assign_null_coalescing_to_coalesce_equal',
                'combine_nested_dirname',
                'get_class_to_class_keyword',
                'heredoc_indentation',
                'list_syntax',
                'modern_serialization_methods',
                'modernize_strpos',
                'modifier_keywords',
                'new_expression_parentheses',
                'no_unneeded_final_method',
                'no_whitespace_before_comma_in_array',
                'non_printable_character',
                'nullable_type_declaration_for_default_null_value',
                'octal_notation',
                'phpdoc_readonly_class_comment_to_keyword',
                'pow_to_exponentiation',
                'random_api_migration',
                'ternary_to_null_coalescing',
                'use_arrow_functions',
                'void_return',
            ],
            fn (SplFileInfo $file): bool => $this->isPHP85($file),
        );
        $declareStrictTypes = new DeclareStrictTypesFixer();
        $declareStrictTypes->configure(['strategy' => 'remove']);
        $customisers['declare_strict_types'] = fn (SplFileInfo $file): bool|FixerInterface => $this->isPHP85($file) ?: $declareStrictTypes;
        $trailingCommaInMultiline = new TrailingCommaInMultilineFixer();
        $trailingCommaInMultiline->configure(['after_heredoc' => false, 'elements' => ['arrays', 'array_destructuring', 'match']]);
        $customisers['trailing_comma_in_multiline'] = fn (SplFileInfo $file): bool|FixerInterface => $this->isPHP85($file) ?: $trailingCommaInMultiline;

        return $customisers;
    }

    private function isPHP85(SplFileInfo $file): bool
    {
        $realPath = (string) $file->getRealPath();

        return $this->isPHP85Cache[$realPath] ??= self::isPHP85RelativePath(str_replace(DIRECTORY_SEPARATOR, '/', substr($realPath, strlen((string) realpath(__DIR__)) + 1)));
    }

    private static function isPHP85RelativePath(string $relativePath): bool
    {
        if (in_array($relativePath, PHP85_FILES, true)) {
            return true;
        }

        return str_starts_with($relativePath, 'scripts/') && !in_array($relativePath, PHP55_FILES, true);
    }
};

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        // Each line of multi-line DocComments must have an asterisk [PSR-5] and must be aligned with the first one.
        'align_multiline_comment' => ['comment_type' => 'all_multiline'],
        // Each element of an array must be indented exactly once.
        'array_indentation' => true,
        // Converts simple usages of `array_push($x, $y);` to `$x[] = $y;`.
        'array_push' => true,
        // PHP arrays should be declared using the configured syntax.
        'array_syntax' => ['syntax' => 'short'],
        // Use the null coalescing assignment operator `??=` where possible.
        'assign_null_coalescing_to_coalesce_equal' => true,
        // Remove spaces before and after the attributes block.
        'attribute_block_no_spaces' => true,
        // PHP attributes declared without arguments must (not) be followed by empty parentheses.
        'attribute_empty_parentheses' => ['use_parentheses' => true],
        // Converts backtick operators to `shell_exec` calls.
        'backtick_to_shell_exec' => true,
        // Binary operators should be surrounded by space as configured.
        'binary_operator_spaces' => ['default' => 'single_space'],
        // There MUST be one blank line after the namespace declaration.
        'blank_line_after_namespace' => true,
        // Ensure there is no code on the same line as the PHP open tag and it is followed by a blank line.
        'blank_line_after_opening_tag' => true,
        // An empty line feed must precede any configured statement.
        'blank_line_before_statement' => ['statements' => ['return']],
        // Putting blank lines between `use` statement groups.
        'blank_line_between_import_groups' => true,
        // Controls blank lines before a namespace declaration.
        'blank_lines_before_namespace' => true,
        // Braces must be placed as configured.
        'braces_position' => ['allow_single_line_anonymous_functions' => true, 'allow_single_line_empty_anonymous_classes' => true],
        // A single space or none should be between cast and variable.
        'cast_spaces' => true,
        // Class, trait and interface elements must be separated with one or none blank line.
        'class_attributes_separation' => ['elements' => ['const' => 'one', 'method' => 'one', 'property' => 'one', 'trait_import' => 'none', 'case' => 'none']],
        // Whitespace around the keywords of a class, trait, enum or interfaces definition should be one space.
        'class_definition' => ['inline_constructor_arguments' => false, 'space_before_parenthesis' => true],
        // When referencing an internal class it must be written using the correct casing.
        'class_reference_name_casing' => true,
        // Namespace must not contain spacing, comments or PHPDoc.
        'clean_namespace' => true,
        // Replace multiple nested calls of `dirname` by only one call with second `$level` parameter.
        'combine_nested_dirname' => true,
        // Comments with annotation should be docblock when used on structural elements.
        'comment_to_phpdoc' => ['ignored_tags' => ['todo']],
        // Remove extra spaces in a nullable type declaration.
        'compact_nullable_type_declaration' => true,
        // Concatenation should be spaced according to configuration.
        'concat_space' => ['spacing' => 'one'],
        // The PHP constants `true`, `false`, and `null` MUST be written using the correct casing.
        'constant_case' => true,
        // The body of each control structure MUST be enclosed within braces.
        'control_structure_braces' => true,
        // Control structure continuation keyword must be on the configured line.
        'control_structure_continuation_position' => true,
        // The first argument of `DateTime::createFromFormat` method must start with `!`.
        'date_time_create_from_format_call' => true,
        // Equal sign in declare statement should be surrounded by spaces or not following configuration.
        'declare_equal_normalize' => true,
        // There must not be spaces around `declare` statement parentheses.
        'declare_parentheses' => true,
        // Force a strict types mode in all files.
        'declare_strict_types' => true,
        // Replaces `dirname(__FILE__)` expression with equivalent `__DIR__` constant.
        'dir_constant' => true,
        // Replaces short-echo `<?=` with long format `<?php echo`/`<?php print` syntax, or vice-versa.
        'echo_tag_syntax' => ['format' => 'short'],
        // The keyword `elseif` should be used instead of `else if` so that all control keywords look like single words.
        'elseif' => true,
        // PHP code MUST use only UTF-8 without BOM (remove BOM).
        'encoding' => true,
        // Replace deprecated `ereg` regular expression functions with `preg`.
        'ereg_to_preg' => true,
        // Add curly braces to indirect variables to make them clear to understand.
        'explicit_indirect_variable' => true,
        // Converts implicit variables into explicit ones in double-quoted strings or heredoc syntax.
        'explicit_string_variable' => true,
        // PHP code must use the long `<?php` tags or short-echo `<?=` tags and not other tag variations.
        'full_opening_tag' => true,
        // Spaces should be properly placed in a function declaration.
        'function_declaration' => true,
        // Replace core functions calls returning constants with the constants.
        'function_to_constant' => true,
        // Renames PHPDoc tags.
        'general_phpdoc_tag_rename' => ['replacements' => ['inheritDocs' => 'inheritdoc', 'inheritDoc' => 'inheritdoc']],
        // Replace `get_class` calls on object variables with class keyword syntax.
        'get_class_to_class_keyword' => true,
        // Imports or fully qualifies global classes/functions/constants.
        'global_namespace_import' => ['import_classes' => false],
        // Heredoc/nowdoc content must be properly indented.
        'heredoc_indentation' => true,
        // Convert `heredoc` to `nowdoc` where possible.
        'heredoc_to_nowdoc' => true,
        // Function `implode` must be called with 2 arguments in the documented order.
        'implode_call' => true,
        // Include/Require and file path should be divided with a single space. File path should not be placed within parentheses.
        'include' => true,
        // Pre- or post-increment and decrement operators should be used if possible.
        'increment_style' => ['style' => 'post'],
        // Code MUST use configured indentation type.
        'indentation_type' => true,
        // Integer literals must be in correct case.
        'integer_literal_case' => true,
        // Replaces `is_null($var)` expression with `null === $var`.
        'is_null' => true,
        // Lambda must not import variables it doesn't use.
        'lambda_not_used_import' => true,
        // All PHP files must use same line ending.
        'line_ending' => true,
        // Ensure there is no code on the same line as the PHP open tag.
        'linebreak_after_opening_tag' => true,
        // List (`array` destructuring) assignment should be declared using the configured syntax.
        'list_syntax' => true,
        // Shorthand notation for operators should be used if possible.
        'long_to_shorthand_operator' => true,
        // Cast should be written in lower case.
        'lowercase_cast' => true,
        // PHP keywords MUST be in lower case.
        'lowercase_keywords' => true,
        // Class static references `self`, `static` and `parent` MUST be in lower case.
        'lowercase_static_reference' => true,
        // Magic constants should be referred to using the correct casing.
        'magic_constant_casing' => true,
        // Magic method definitions and calls must be using the correct casing.
        'magic_method_casing' => true,
        // In method arguments and method call, there MUST NOT be a space before each comma and there MUST be one space after each comma. Argument lists MAY be split across multiple lines, where each subsequent line is indented once. When doing so, the first item in the list MUST be on the next line, and there MUST be only one argument per line.
        'method_argument_space' => true,
        // Use new serialization methods `__serialize` and `__unserialize` instead of deprecated ones `__sleep` and `__wakeup`.
        'modern_serialization_methods' => true,
        // Replace `strpos()` and `stripos()` calls with `str_starts_with()` or `str_contains()` if possible.
        'modernize_strpos' => true,
        // Replaces `intval`, `floatval`, `doubleval`, `strval` and `boolval` function calls with according type casting operator.
        'modernize_types_casting' => true,
        // Classes, constants, properties, and methods MUST have visibility declared, and keyword modifiers MUST be in the following order: inheritance modifier (`abstract` or `final`), visibility modifier (`public`, `protected`, or `private`), set-visibility modifier (`public(set)`, `protected(set)`, or `private(set)`), scope modifier (`static`), mutation modifier (`readonly`), type declaration, name.
        'modifier_keywords' => true,
        // DocBlocks must start with two asterisks, multiline comments must start with a single asterisk, after the opening slash. Both must end with a single asterisk before the closing slash.
        'multiline_comment_opening_closing' => true,
        // Promoted properties must be on separate lines.
        'multiline_promoted_properties' => true,
        // Convert multiline string to `heredoc` or `nowdoc`.
        'multiline_string_to_heredoc' => true,
        // Forbid multi-line whitespace before the closing semicolon or move the semicolon to the new line for chained calls.
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        // Function defined by PHP should be called using the correct casing.
        'native_function_casing' => true,
        // Native type declarations should be used in the correct case.
        'native_type_declaration_casing' => true,
        // All `new` expressions with a further call must (not) be wrapped in parentheses.
        'new_expression_parentheses' => true,
        // All instances created with `new` keyword must (not) be followed by parentheses.
        'new_with_parentheses' => ['anonymous_class' => false, 'named_class' => true],
        // Master functions shall be used instead of aliases.
        'no_alias_functions' => true,
        // Replace control structure alternative syntax to use braces.
        'no_alternative_syntax' => ['fix_non_monolithic_code' => true],
        // There should not be a binary flag before strings.
        'no_binary_string' => true,
        // There should be no empty lines after class opening brace.
        'no_blank_lines_after_class_opening' => true,
        // There must be a comment when fall-through is intentional in a non-empty case body.
        'no_break_comment' => true,
        // The closing `? >` tag MUST be omitted from files containing only PHP.
        'no_closing_tag' => true,
        // There should not be any empty comments.
        'no_empty_comment' => true,
        // There should not be empty PHPDoc blocks.
        'no_empty_phpdoc' => true,
        // Remove useless (semicolon) statements.
        'no_empty_statement' => true,
        // Removes extra blank lines and/or blank lines following configuration.
        'no_extra_blank_lines' => ['tokens' => ['attribute', 'case', 'continue', 'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block', 'square_brace_block', 'switch', 'throw', 'use']],
        // Replace accidental usage of homoglyphs (non ascii characters) in names.
        'no_homoglyph_names' => true,
        // Remove leading slashes in `use` clauses.
        'no_leading_import_slash' => true,
        // The namespace declaration line shouldn't contain leading whitespace.
        'no_leading_namespace_whitespace' => true,
        // Either language construct `print` or `echo` should be used.
        'no_mixed_echo_print' => true,
        // Operator `=>` should not be surrounded by multi-line whitespaces.
        'no_multiline_whitespace_around_double_arrow' => true,
        // There must not be more than one statement per line.
        'no_multiple_statements_per_line' => true,
        // Convert PHP4-style constructors to `__construct`.
        'no_php4_constructor' => true,
        // Short cast `bool` using double exclamation mark should not be used.
        'no_short_bool_cast' => true,
        // Single-line whitespace before closing semicolon are prohibited.
        'no_singleline_whitespace_before_semicolons' => true,
        // There must be no space around double colons (also called Scope Resolution Operator or Paamayim Nekudotayim).
        'no_space_around_double_colon' => true,
        // When making a method or function call, there MUST NOT be a space between the method or function name and the opening parenthesis.
        'no_spaces_after_function_name' => true,
        // There MUST NOT be spaces around offset braces.
        'no_spaces_around_offset' => true,
        // Replaces superfluous `elseif` with `if`.
        'no_superfluous_elseif' => true,
        // Removes `@param`, `@return` and `@var` tags that don't provide any useful information.
        'no_superfluous_phpdoc_tags' => ['allow_hidden_params' => false, 'allow_unused_params' => false, 'remove_inheritdoc' => false, 'allow_mixed' => true],
        // If a list of values separated by a comma is contained on a single line, then the last item MUST NOT have a trailing comma.
        'no_trailing_comma_in_singleline' => true,
        // There must be no trailing whitespace at the end of non-blank lines.
        'no_trailing_whitespace' => true,
        // There must be no trailing whitespace at the end of lines in comments and PHPDocs.
        'no_trailing_whitespace_in_comment' => true,
        // Removes unneeded braces that are superfluous and aren't part of a control structure's body.
        'no_unneeded_braces' => true,
        // Removes unneeded parentheses around control statements.
        'no_unneeded_control_parentheses' => true,
        // Removes `final` from methods where possible.
        'no_unneeded_final_method' => true,
        // Imports should not be aliased as the same name.
        'no_unneeded_import_alias' => true,
        // In function arguments there must not be arguments with default values before non-default ones.
        'no_unreachable_default_argument_value' => true,
        // Variables must be set `null` instead of using `(unset)` casting.
        'no_unset_cast' => true,
        // Unused `use` statements must be removed.
        'no_unused_imports' => true,
        // There should not be useless concat operations.
        'no_useless_concat_operator' => true,
        // There should not be useless `else` cases.
        'no_useless_else' => true,
        // There should not be useless Null-safe operator `?->` used.
        'no_useless_nullsafe_operator' => true,
        // There must be no `printf` calls with only the first argument.
        'no_useless_printf' => true,
        // There should not be an empty `return` statement at the end of a function.
        'no_useless_return' => true,
        // There must be no `sprintf` calls with only the first argument.
        'no_useless_sprintf' => true,
        // In array declaration, there MUST NOT be a whitespace before each comma.
        'no_whitespace_before_comma_in_array' => ['after_heredoc' => true],
        // Remove trailing whitespace at the end of blank lines.
        'no_whitespace_in_blank_line' => true,
        // Remove Zero-width space (ZWSP), Non-breaking space (NBSP) and other invisible unicode symbols.
        'non_printable_character' => true,
        // Array index should always be written by using square braces.
        'normalize_index_brace' => true,
        // Nullable single type declaration should be standardised using configured syntax.
        'nullable_type_declaration' => ['syntax' => 'question_mark'],
        // Adds or removes `?` before single type declarations or `|null` at the end of union types when parameters have a default `null` value.
        'nullable_type_declaration_for_default_null_value' => true,
        // There should not be space before or after object operators `->` and `?->`.
        'object_operator_without_whitespace' => true,
        // Literal octal must be in `0o` notation.
        'octal_notation' => true,
        // Operators - when multiline - must always be at the beginning or at the end of the line.
        'operator_linebreak' => true,
        // Sorts attributes using the configured sort algorithm.
        'ordered_attributes' => true,
        // Orders the elements of classes/interfaces/traits/enums.
        'ordered_class_elements' => ['order' => ['use_trait']],
        // Ordering `use` statements.
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        // Sort union types and intersection types using configured order.
        'ordered_types' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        // PHPUnit assertion method calls like `->assertSame(true, $foo)` should be written with dedicated method like `->assertTrue($foo)`.
        'php_unit_construct' => true,
        // PHPUnit annotations should be a FQCNs including a root namespace.
        'php_unit_fqcn_annotation' => true,
        // Enforce camel (or snake) case for PHPUnit test methods, following configuration.
        'php_unit_method_casing' => true,
        // Calls to `PHPUnit\Framework\TestCase` static methods (like assertions) must all be of the same type, either `$this->`, `self::` or `static::`.
        'php_unit_test_case_static_method_calls' => ['call_type' => 'static'],
        // All items of the given PHPDoc tags must be either left-aligned or (by default) aligned vertically.
        'phpdoc_align' => ['align' => 'left'],
        // PHPDoc annotation descriptions should not be a sentence.
        'phpdoc_annotation_without_dot' => true,
        // Docblocks should have the same indentation as the documented subject.
        'phpdoc_indent' => true,
        // Changes doc blocks from single to multi line, or reversed.
        'phpdoc_line_span' => ['const' => 'multi', 'method' => 'multi', 'property' => 'multi'],
        // Classy that does not inherit must not have `@inheritdoc` tags.
        'phpdoc_no_useless_inheritdoc' => true,
        // Annotations in PHPDoc should be ordered in defined sequence.
        'phpdoc_order' => true,
        // Orders all `@param` annotations in DocBlocks according to method signature.
        'phpdoc_param_order' => true,
        // Converts readonly comment on classes to the readonly keyword.
        'phpdoc_readonly_class_comment_to_keyword' => true,
        // The type of `@return` annotations of methods returning a reference to itself must the configured one.
        'phpdoc_return_self_reference' => true,
        // Scalar types should always be written in the same form. `int` not `integer`, `bool` not `boolean`, `float` not `real` or `double`.
        'phpdoc_scalar' => true,
        // Single line `@var` PHPDoc should have proper spacing.
        'phpdoc_single_line_var_spacing' => true,
        // PHPDoc summary should end in either a full stop, exclamation mark, or question mark.
        'phpdoc_summary' => true,
        // Fixes casing of PHPDoc tags.
        'phpdoc_tag_casing' => ['tags' => ['inheritdoc']],
        // Forces PHPDoc tags to be either regular annotations or inline.
        'phpdoc_tag_type' => ['tags' => ['inheritdoc' => 'inline']],
        // Docblocks should only be used on structural elements.
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        // PHPDoc should start and end with content, excluding the very first and last line of the docblocks.
        'phpdoc_trim' => true,
        // Removes extra blank lines after summary and after description in PHPDoc.
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        // The correct case must be used for standard PHP types in PHPDoc.
        'phpdoc_types' => true,
        // Sorts PHPDoc types.
        'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
        // `@var` and `@type` annotations must have type and name in the correct order.
        'phpdoc_var_annotation_correct_order' => true,
        // `@var` and `@type` annotations of classy properties should not contain the name.
        'phpdoc_var_without_name' => true,
        // Converts `pow` to the `**` operator.
        'pow_to_exponentiation' => true,
        // Classes must be in a path that matches their namespace, be at least one namespace deep and the class name should match the file name.
        'psr_autoloading' => true,
        // Replaces `rand`, `srand`, `getrandmax` functions calls with their `mt_*` analogs or `random_int`.
        'random_api_migration' => ['replacements' => ['mt_getrandmax' => 'getrandmax', 'mt_rand' => 'random_int', 'mt_srand' => 'srand', 'rand' => 'random_int']],
        // Local, dynamic and directly referenced variables should not be assigned and directly returned by a function or method.
        'return_assignment' => true,
        // Adjust spacing around colon in return type declarations and backed enum types.
        'return_type_declaration' => true,
        // Inside class or interface element `self` should be preferred to the class name itself.
        'self_accessor' => true,
        // Inside an enum or `final`/anonymous class, `self` should be preferred over `static`.
        'self_static_accessor' => true,
        // Instructions must be terminated with a semicolon.
        'semicolon_after_instruction' => true,
        // Cast shall be used, not `settype`.
        'set_type_to_cast' => true,
        // Cast `(boolean)` and `(integer)` should be written as `(bool)` and `(int)`, `(double)` and `(real)` as `(float)`, `(binary)` as `(string)`.
        'short_scalar_cast' => true,
        // Converts explicit variables in double-quoted strings and heredoc syntax from simple to complex format (`${` to `{$`).
        'simple_to_complex_string_variable' => true,
        // A PHP file without end tag must always end with a single empty line feed.
        'single_blank_line_at_eof' => true,
        // There MUST NOT be more than one property or constant declared per statement.
        'single_class_element_per_statement' => true,
        // There MUST be one use keyword per declaration.
        'single_import_per_statement' => ['group_to_single_imports' => true],
        // Each namespace use MUST go on its own line and there MUST be one blank line after the use statements block.
        'single_line_after_imports' => true,
        // Single-line comments must have proper spacing.
        'single_line_comment_spacing' => true,
        // Single-line comments and multi-line comments with only one line of actual content should use the `//` syntax.
        'single_line_comment_style' => true,
        // Convert double quotes to single quotes for simple strings.
        'single_quote' => true,
        // Ensures a single space after language constructs.
        'single_space_around_construct' => ['constructs_followed_by_a_single_space' => ['abstract', 'as', 'case', 'catch', 'class', 'const', 'const_import', 'do', 'else', 'elseif', 'enum', 'final', 'finally', 'for', 'foreach', 'function', 'function_import', 'if', 'insteadof', 'interface', 'match', 'named_argument', 'namespace', 'new', 'private', 'protected', 'public', 'readonly', 'static', 'switch', 'trait', 'try', 'type_colon', 'use', 'use_lambda', 'while'], 'constructs_preceded_by_a_single_space' => ['as', 'else', 'elseif', 'use_lambda']],
        // Each trait `use` must be done as single statement.
        'single_trait_insert_per_statement' => true,
        // Fix whitespace after a semicolon.
        'space_after_semicolon' => ['remove_in_empty_for_expressions' => true],
        // Parentheses must be declared using the configured whitespace.
        'spaces_inside_parentheses' => ['space' => 'none'],
        // Increment and decrement operators should be used if possible.
        'standardize_increment' => true,
        // Replace all `<>` with `!=`.
        'standardize_not_equals' => true,
        // Each statement must be indented.
        'statement_indentation' => true,
        // Lambdas not (indirectly) referencing `$this` must be declared `static`.
        'static_lambda' => true,
        // String tests for empty must be done against `''`, not with `strlen`.
        'string_length_to_empty' => true,
        // All multi-line strings must use correct line ending.
        'string_line_ending' => true,
        // A case should be followed by a colon and not a semicolon.
        'switch_case_semicolon_to_colon' => true,
        // Removes extra spaces between colon and case value.
        'switch_case_space' => true,
        // Switch case must not be ended with `continue` but with `break`.
        'switch_continue_to_break' => true,
        // Standardize spaces around ternary operator.
        'ternary_operator_spaces' => true,
        // Use the Elvis operator `?:` where possible.
        'ternary_to_elvis_operator' => true,
        // Use `null` coalescing operator `??` where possible.
        'ternary_to_null_coalescing' => true,
        // Arguments lists, array destructuring lists, arrays that are multi-line, `match`-lines and parameters lists must have a trailing comma.
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters']],
        // Arrays should be formatted like function/method arguments, without leading or trailing single line space.
        'trim_array_spaces' => true,
        // Ensure single space between a variable and its type declaration in function arguments and properties.
        'type_declaration_spaces' => true,
        // A single space or none should be around union type and intersection type operators.
        'types_spaces' => true,
        // Unary operators should be placed adjacent to their operands.
        'unary_operator_spaces' => true,
        // Anonymous functions with return as the only statement must use arrow functions.
        'use_arrow_functions' => true,
        // Add `void` return type to functions with missing or empty return statements, but priority is given to `@return` annotations.
        'void_return' => true,
        // In array declaration, there MUST be a whitespace after each comma.
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        // Write conditions in Yoda style (`true`), non-Yoda style (`['equal' => false, 'identical' => false, 'less_and_greater' => false]`) or ignore those conditions (`null`) based on configuration.
        'yoda_style' => ['always_move_variable' => false, 'equal' => false, 'identical' => false, 'less_and_greater' => false],
    ])
    ->setRuleCustomisationPolicy($php55Policy)
    ->setFinder(
        new PhpCsFixer\Finder()
            ->in([
                __DIR__ . '/scripts',
                __DIR__ . '/src',
                __DIR__ . '/test',
            ])
            ->append([
                __FILE__,
                __DIR__ . '/bin/build-phar',
                __DIR__ . '/bin/install-php-extensions',
            ]),
    )
;
