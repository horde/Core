<?php


$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PHP83Migration' => true,
    '@PSR12' => true,
    'single_quote' => true,
    'nullable_type_declaration_for_default_null_value' => true,
]);
