<?php

namespace Dparadiz\Codegen;

use Mustache_Engine;

final class Renderer
{

    public function __construct(private readonly Mustache_Engine $renderer)
    {
    }

    public function process(Stack $stack, string $outputFolder, string $namespaceBase = 'Api'): void
    {
        while (!$stack->isEmpty()) {
            $stackItem = $stack->pop();

            $filename = trim(str_replace('\\', '/', $stackItem->className), '/');

            $filename .= '.' . ($stackItem->ext !== '' ? $stackItem->ext : 'php');

            $file = new \SplFileInfo(rtrim($outputFolder, '/') . '/' . $filename);
            if (!file_exists($file->getPath())) {
                mkdir($file->getPath(), 0777, true);
            }
            $templateData = $stackItem->templateData;
            $templateData['namespaceBase'] = trim($namespaceBase, '\\') . '\\';

            file_put_contents($file->getPathname(), $this->renderer->render($stackItem->template, $templateData));
        }
    }
}
