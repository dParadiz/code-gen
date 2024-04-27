<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData;

class EnumItem
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    )
    {

    }
}
