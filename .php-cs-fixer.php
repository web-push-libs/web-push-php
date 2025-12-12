<?php
// https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst

$config = new PhpCsFixer\Config();
$rules  = [
    '@PSR12'                              => true, // The default rule.
    '@autoPHPMigration'                   => true, // Uses min PHP version for regular migrations.
    'blank_line_after_opening_tag'        => false, // Do not waste space between <?php and declare.
    'global_namespace_import'             => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
    'php_unit_construct'                  => true,
    'php_unit_attributes'                 => true,
    'php_unit_method_casing'              => true,
    'php_unit_test_class_requires_covers' => true,
    // Do not enable by default. These rules require review!! (but they are useful)
    // '@autoPHPMigration:risky'      => true,
    // '@autoPHPUnitMigration:risky' => true,
];

$config->setRules($rules);
$config->setHideProgress(true);
$config->setRiskyAllowed(true);

return $config;
