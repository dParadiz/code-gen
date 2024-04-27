<?php declare(strict_types=1);

namespace Dparadiz\Codegen\Generator;

final class StackItem
{

    public function __construct(
        public readonly string $template,
        public readonly string $className,
        public object|array    $templateData = [],
        public string          $ext = ''
    )
    {
    }
}
