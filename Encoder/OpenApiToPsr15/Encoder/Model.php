<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\Encoder;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\Enum;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\EnumItem;
use Dparadiz\Codegen\Generator\Stack;
use Dparadiz\Codegen\Generator\StackItem;
use Dparadiz\Codegen\PropertyDataMapper;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData;

class Model
{

    public function __construct(
        private readonly string $modelNamespace,
    )
    {

    }

    public function encode(string $modelName, Schema $schema, Stack $stack): void
    {

        $modelName = ucfirst(str_replace('.json', '', $modelName));

        $stackItem = new StackItem('model', $this->modelNamespace . '\\' . $modelName);
        $stackItem->templateData = $this->getModelData($modelName, $schema, $stack);
        $stack->push($stackItem);
    }

    private function getModelData(string $modelName, Schema $schema, Stack $stack): TemplateData\Model
    {
        $data = new TemplateData\Model($modelName, $this->modelNamespace);

        if (isset($schema->{'x-constraint'})) {
            $data->attributes[] = $schema->{'x-constraint'};
            $data->imports[] = 'Validator as ApiConstrain';
        }

        if ($schema->type === 'object') {
            $required = array_intersect_key($schema->properties, array_flip($schema->required ?? []));

            foreach ($required as $pName => $property) {
                $this->setModelProperties($data, $pName, $property, $schema, $stack, $modelName);
            }

            $optional = array_diff_key($schema->properties, array_flip($schema->required ?? []));

            foreach ($optional as $pName => $property) {
                $this->setModelProperties($data, $pName, $property, $schema, $stack, $modelName);
            }
        } elseif (!empty($schema->allOf)) {
            foreach ($schema->allOf as $schemaPartsRef) {
                if (!($schemaPartsRef instanceof Reference)) {
                    continue;
                }

                $schemaPart = $schemaPartsRef->resolve();
                if (!($schemaPart instanceof Schema)) {
                    continue;
                }

                $required = [];
                $optional = [];
                if ($schemaPart->type === 'object') {
                    $required = array_intersect_key(
                        $schemaPart->properties,
                        array_flip($schemaPart->required ?? $required)
                    );


                    $optional = array_diff_key(
                        $schemaPart->properties,
                        array_flip($schemaPart->required ?? $optional)
                    );
                }

                foreach ($required as $pName => $property) {
                    $this->setModelProperties($data, $pName, $property, $schema, $stack, $modelName);
                }

                foreach ($optional as $allOfPName => $allOfProperty) {
                    $this->setModelProperties($data, $allOfPName, $allOfProperty, $schema, $stack, $modelName);
                }
            }
        }
        return $data;
    }

    private function setModelProperties(TemplateData\Model $data, string $pName, Schema|Reference $property, Schema $schema, Stack $stack, string $parentModelName): void
    {
        $pName = str_replace('.json', '', $pName);
        $propertyDataMapper = new PropertyDataMapper();
        $isRequired = in_array($pName, $schema->required ?? []);
        $modelProperty = $propertyDataMapper->getProperty($pName, $property, $isRequired);

        // add property models to stack
        if ($property instanceof Reference) {
            $name = $this->getModelNameFromRef($property->getReference());
            $property = $property->resolve();
            if ($property instanceof Schema && $name !== $parentModelName) {
                $this->encode($name, $property, $stack);
            }
        }

        // add array item
        if ($property->type === 'array') {
            if ($property->items instanceof Reference) {
                $name = $this->getModelNameFromRef($property->items->getReference());
                $item = $property->items->resolve();

                if ($item instanceof Schema && $name !== $parentModelName) {
                    $this->encode($name, $item, $stack);
                }
            }
        }

        if (!empty($property->enum)) {
            $enumName = ucfirst($pName);

            $stackItem = new StackItem('enum', $this->modelNamespace . '\\Enum\\' . $enumName);
            $isString = $property->type === 'string';

            $stackItem->templateData = new Enum(
                className: $enumName,
                namespace: $this->modelNamespace . '\\Enum',
                isString: $isString,
                type: $isString ? 'string' : 'int',
                enums: array_map(fn($value) => new EnumItem(
                    preg_replace("/[^a-zA-Z0-9]+/", "", $value),
                    $value
                ), array_filter($property->enum))
            );
            $stack->push($stackItem);

        }

        if (!empty($property->oneOf)) {
            foreach ($property->oneOf as $oneOfSchema) {
                $name = $this->getModelNameFromRef($oneOfSchema->getReference());

                $oneOfSchema = $oneOfSchema->resolve();

                if ($oneOfSchema instanceof Schema && $name !== $parentModelName) {
                    $this->encode($name, $oneOfSchema, $stack);
                }
            }
        }

        $data->properties[] = get_object_vars($modelProperty);
    }

    private function getModelNameFromRef(string $ref): string
    {
        $refParts = explode('/', $ref);

        return ucfirst(str_replace('.json', '', end($refParts)));
    }
}