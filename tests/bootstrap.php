<?php

declare(strict_types=1);

// remove the references file because of problems with phpunit --process-isolation
if (file_exists(__DIR__ . '/Fixtures/Symfony/app/config/reference.php')) {
    unlink(__DIR__ . '/Fixtures/Symfony/app/config/reference.php');
}

require_once __DIR__ . '/../vendor/autoload.php';
