<?php
// https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst

$config = new PhpCsFixer\Config();
$rules  = [
    '@PhpCsFixer'             => true, // Very strict.
    '@PHP80Migration'         => true, // Must be the same as the min PHP version.
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => true],
    // Do not enable by default. These rules require review!! (but they are useful)
    // '@PhpCsFixer:risky'     => true,
    // '@PHP80Migration:risky' => true,
];

return $config->setRules($rules);
