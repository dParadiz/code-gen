<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData;

class Model
{
    public function __construct(
        public readonly string $className,
        public readonly string $namespace,
        public array           $imports = [],
        public array           $properties = [],
        public array           $attributes = [],
    )
    {

    }
}