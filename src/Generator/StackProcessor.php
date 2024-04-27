<?php declare(strict_types=1);

namespace Dparadiz\Codegen\Generator;

use Dparadiz\Codegen\CodeWriterInterface;

final readonly class StackProcessor
{

    public function __construct(
        private CodeWriterInterface $writer,
        private string              $outputFolder,
        private string $namespace,
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

            if (!is_dir($file->getPath())) {
                mkdir($file->getPath(), 0777, true);
            }
            $templateData = $stackItem->templateData ?? [];

            $templateData = is_object($templateData) ? get_object_vars($templateData) : $templateData;
            $templateData['baseNamespace'] = $this->namespace;

            file_put_contents($file->getPathname(), $this->writer->generate($stackItem->template, $templateData));
        }
    }
}
