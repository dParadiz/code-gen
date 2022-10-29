<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData;

class PathParameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $docType,
        public readonly string $type,
        public readonly bool   $required,
        public readonly bool   $inQuery,
        public readonly bool   $inPath,
        public readonly bool   $useEnum,
        public readonly array  $attributes,
    )
    {
    }
}