<?php declare(strict_types=1);

namespace Dparadiz\Codegen;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class TypeResolver
{
    private function getPhpType(string $type, string $format): string
    {
        return match ($type) {
            'number', 'float' => 'float',
            'int', 'integer' => 'int',
            'boolean' => 'bool',
            'array', 'object' => 'array',
            'string' => match (true) {
                in_array($format, ['date-time', 'date', 'datetime']) => '\DateTimeImmutable',
                default => 'string'
            },
            default => throw new \InvalidArgumentException($type . ' can not be mapped to php type')
        };
    }

    public function getType(Reference|Schema $schema, string $name): string
    {
        if (!($schema instanceof Reference) && $schema->type === null) {
            echo "Type not set on $name defaulting to string\n";
            $schema->type = 'string';

        }

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
            default => $this->getPhpType($this->getSchemaType($schema->type), $this->getFormat($schema->format))
        };
    }

    private function getSchemaType(mixed $type): string
    {
        if (is_string($type)) {
            return $type;
        } elseif (is_array($type)) {
            echo "Converting type as array to first string of first array element: " . json_encode($type) . "\n";
            return reset($type);
        } else {
            return '';
        }
    }

    private function getFormat(mixed $format): string
    {
        if (is_array($format)) {
            echo "Using first array value as type: " . json_encode($format) . "\n";
            return reset($format);
        } elseif (is_string($format)) {
            return $format;
        } else {
            return '';
        }
    }

    public function getArrayDocType(Schema|Reference $schema): string
    {
        return match (true) {
            $schema->items instanceof Reference => $this->getModelNameFromRef($schema->items->getReference()) . '[]',
            $schema->additionalProperties => 'array', // associative array
            default => $this->getPhpType($schema->items->type, '') . '[]'
        };
    }

    private function getModelNameFromRef(string $ref): string
    {
        $refParts = explode('/', $ref);

        return ucfirst(str_replace('.json', '', end($refParts)));
    }
}
