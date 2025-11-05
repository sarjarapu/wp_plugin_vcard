<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/minisite-manager.php')
    ->exclude('vendor')
    ->exclude('tests')
    ->exclude('build')
    ->exclude('scripts')
    ->exclude('templates');

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'long'],
        'linebreak_after_opening_tag' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ignore',
        ],
        'line_ending' => true,
    ])
    ->setLineLengthLimit(120)
    ->setLineEnding("\n")
    ->setIndent("\t")
    ->setRiskyAllowed(true)
    ->setFinder($finder);

