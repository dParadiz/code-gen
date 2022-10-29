<?php


use Dparadiz\Codegen\GenerateApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class GenerateApiTest extends TestCase
{
    public function test_generate_api(): void
    {

        $console = new Application();
        $console->add(new GenerateApi('generate:api'));

        $input = new ArrayInput([
            'command' => 'generate:api',
            '-s' => __DIR__ .'/../Resources/petstore.yaml',
            '-o' => __DIR__ .'/../tmp/src',
        ]);
        $output = new ConsoleOutput();
        $console->run($input, $output);
    }
}