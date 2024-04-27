<?php declare(strict_types=1);

namespace Dparadiz\Codegen;

interface CodeWriterInterface
{
    public function generate(string $template, array|object $data): string;
}