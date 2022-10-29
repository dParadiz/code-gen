<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData;

class Enum
{
    /** @param $enums EnumItem[] */
    public function __construct(
        public readonly string $className,
        public readonly string $namespace,
        public readonly bool   $isString,
        public readonly string $type,
        public readonly array  $enums = [],
    )
    {

    }
}
