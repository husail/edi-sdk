<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'operator_linebreak' => ['only_booleans' => false, 'position' => 'beginning'],
        'control_structure_braces' => false,
        'class_attributes_separation' => false,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'no_superfluous_phpdoc_tags' => false,
        'phpdoc_align' => ['align' => 'left'],
        'concat_space' => ['spacing' => 'one'],
        'ordered_imports' => [
            'sort_algorithm' => 'length',
            'imports_order' => ['const', 'class', 'function'],
        ],
    ]);
