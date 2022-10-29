<?php

namespace Dparadiz\Codegen\CodeWriter;

use Dparadiz\Codegen\CodeWriterInterface;
use Mustache_Engine;
use Mustache_LambdaHelper;

class MustacheEngineAdapter implements CodeWriterInterface
{

    private Mustache_Engine $mustacheEngine;

    public function __construct(string $templatePath)
    {
        $this->mustacheEngine = new Mustache_Engine([
            'entity_flags' => ENT_QUOTES,
            'helpers' => [
                'ucfirst' => fn(string $text, Mustache_LambdaHelper $helper) => ucfirst($helper->render($text)),
                'rTrim' => fn(string $text, Mustache_LambdaHelper $helper) => preg_replace('/(,|\||\n) ?$/', '', $helper->render($text)),
                'phpOpenTag' => '<?php',
            ],
        ]);
        $this->mustacheEngine->setLoader(new \Mustache_Loader_FilesystemLoader($templatePath));
    }

    public function generate(string $template, array|object $data): string
    {
        return $this->mustacheEngine->render($template, $data);
    }
}