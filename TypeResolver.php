<?php

namespace Dparadiz\Codegen;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class TypeResolver
{
    private function getPhpType(string $type, string $format): string
    {
        return match ($type) {
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array', 'object' => 'array',
            'string' => match (true) {
                in_array($format, ['date-time', 'date']) => '\DateTimeImmutable',
                default => 'string'
            },
            default => throw new \InvalidArgumentException($type . ' can not be mapped to php type')
        };
    }

    public function getType(Reference|Schema $schema, string $name): string
    {
        return match (true) {
            $schema instanceof Reference => $this->getModelNameFromRef($schema->getReference()),
            !empty($schema->enum) => 'Enum\\' . ucfirst($name),
            !empty($schema->oneOf) => implode(
                '|',
                array_unique(
                    array_map(
                        fn(Reference $oneOfRef) => $this->getModelNameFromRef($oneOfRef->getReference()),
                        $schema->oneOf
                    )
                )
            ),
            default => $this->getPhpType($schema->type, $schema->format ?? '')
        };
    }

    public function getArrayDocType(Schema|Reference $schema): string
    {
        return match (true) {
            $schema->items instanceof Reference => $this->getModelNameFromRef($schema->items->getReference()) . '[]',
            $schema->additionalProperties => 'array', // associative array
            default => $this->getPhpType($schema->items->type, $property->items->format ?? '') . '[]'
        };
    }


    private function getModelNameFromRef(string $ref): string
    {
        $refParts = explode('/', $ref);

        return end($refParts);
    }
}
