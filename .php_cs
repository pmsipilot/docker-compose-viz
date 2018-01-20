<?php

use PhpCsFixer as CS;

$finder = CS\Finder::create()
    ->in(__DIR__.DIRECTORY_SEPARATOR.'src')
    ->in(__DIR__.DIRECTORY_SEPARATOR.'spec')
;

return CS\Config::create()
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
