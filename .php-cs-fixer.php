<?php
// https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst

$config = new PhpCsFixer\Config();
$rules  = [
    '@PER-CS3x0'                          => true, // The default rule.
    '@autoPHPMigration'                   => true, // Uses min PHP version for regular migrations.
    'blank_line_after_opening_tag'        => false, // Do not waste space between <?php and declare.
    'concat_space'                        => ['spacing' => 'none'], // Custom library style.
    'declare_strict_types'                => true, // Enforce strict code.
    'global_namespace_import'             => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
    'ordered_imports'                     => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
    'php_unit_attributes'                 => true,
    'php_unit_construct'                  => true,
    'php_unit_method_casing'              => true,
    'php_unit_test_class_requires_covers' => true,
    // Do not enable by default. These rules require review!! (but they are useful)
    // '@autoPHPMigration:risky'      => true,
    // '@autoPHPUnitMigration:risky' => true,
];

$config->setRules($rules);
$config->setHideProgress(true);
$config->setRiskyAllowed(true);
$config->setUsingCache(false);
$config->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());

return $config;
