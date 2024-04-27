#!/usr/local/bin/php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dparadiz\Codegen\Command\GenerateApi;
use Symfony\Component\Console\Application;

$console = new Application();
$console->add(new GenerateApi('generate:api'));

try {
    $console->run();
} catch (Exception $e) {
    echo "Console exception: {$e->getMessage()} in {$e->getFile()} ({$e->getLine()})\n";
}