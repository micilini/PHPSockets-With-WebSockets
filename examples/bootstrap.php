<?php

declare(strict_types=1);

$autoloadCandidates = [
    // Repository clone:
    // php-websockets/vendor/autoload.php
    __DIR__ . '/../vendor/autoload.php',

    // Composer package install:
    // project/vendor/micilini/php-websockets/examples/../../../autoload.php
    __DIR__ . '/../../../autoload.php',

    // Fallback when executed from the consumer project root:
    // project/vendor/autoload.php
    getcwd() . '/vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        return;
    }
}

throw new RuntimeException(
    'Composer autoload file was not found. '
    . 'Run "composer install" in the repository root, '
    . 'or install micilini/php-websockets through Composer before running the examples.'
);
