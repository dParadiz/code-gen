<?php

namespace Dparadiz\Codegen;

final class StackItem
{

    public function __construct(
        public string $template,
        public string $className,
        public array  $templateData = [],
        public string $ext = ''
    )
    {
    }
}
