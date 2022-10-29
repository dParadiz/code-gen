<?php

namespace Dparadiz\Codegen\Generator;

use Dparadiz\Codegen\CodeWriterInterface;

final class StackProcessor
{

    public function __construct(
        private readonly CodeWriterInterface $writer,
        private readonly string              $outputFolder,
    )
    {
    }

    public function process(Stack $stack): void
    {
        while (!$stack->isEmpty()) {
            $stackItem = $stack->pop();

            $filename = trim(str_replace('\\', '/', $stackItem->className), '/');

            $filename .= '.' . ($stackItem->ext !== '' ? $stackItem->ext : 'php');

            $file = new \SplFileInfo(rtrim($this->outputFolder, '/') . '/' . $filename);
            if (!file_exists($file->getPath())) {
                mkdir($file->getPath(), 0777, true);
            }
            $templateData = $stackItem->templateData ?? [];

            $templateData = is_object($templateData) ? get_object_vars($templateData) : $templateData;
            $templateData['baseNamespace'] = 'Product\\Api\\';

            file_put_contents($file->getPathname(), $this->writer->generate($stackItem->template, $templateData));
        }
    }
}
