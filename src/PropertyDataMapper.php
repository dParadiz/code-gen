<?php declare(strict_types=1);

namespace Dparadiz\Codegen;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;

class PropertyDataMapper
{
    private const NULL_PREFIX = 'null|';

    public function getProperty(string $name, Schema|Reference $property, bool $isRequired = false): ModelProperty
    {
        $typeResolver = new TypeResolver();

        $type = $typeResolver->getType($property, $name);

        $modelProperty = new ModelProperty($name, $type);

        $modelProperty->isNullable = !$isRequired;
        $nullPrefix = $modelProperty->isNullable ? '?' : '';

        if ($type === 'array') {
            $nullPrefix = $modelProperty->isNullable ? self::NULL_PREFIX : '';
            $modelProperty->docType = $typeResolver->getArrayDocType($property);
        }

        $modelProperty->docType = $nullPrefix . $modelProperty->docType;

        $modelProperty->attributes = $this->getValidationAttribute($property, $modelProperty);

        $modelProperty->defaultValue = match (true) {
            $type === 'array' && !$isRequired && !$property->nullable => '[]', // TODO: handle nulls for arrays
            $modelProperty->isNullable => 'null',
            $type === 'string' && $property->default !== null => "'{$property->default}'",
            $type === 'bool' && $property->default !== null => $property->default ? 'true' : 'false',
            default => $property->default ?? null
        };

        return $modelProperty;
    }

    private function getValidationAttribute(Schema|Reference $property, ModelProperty $modelProperty): array
    {
        $validationRules = [];
        $validationRulesProperties = ['minLength', 'maxLength', 'minimum', 'maximum', 'minItems', 'maxItems'];
        foreach ($validationRulesProperties as $propertyCheck) {
            if (isset($property->{$propertyCheck})) {
                $validationRules[$propertyCheck] = $property->{$propertyCheck};
            }
        }

        $assets = [];
        foreach ($validationRules as $rule => $value) {
            match ($rule) {
                'minimum' => $assets['GreaterThanOrEqual'] = [
                    'value' => $value,
                ],
                'maximum' => $assets['LessThanOrEqual'] = [
                    'value' => $value,
                ],
                'minLength' => $assets['Length'] = array_merge($assets['Length'] ?? [], [
                    'min' => $value,
                ]),
                'maxLength' => $assets['Length'] = array_merge($assets['Length'] ?? [], [
                    'max' => $value,
                ]),
                'minItems' => $assets['Count'] = array_merge($assets['Count'] ?? [], [
                    'min' => $value,
                ]),
                'maxItems' => $assets['Count'] = array_merge($assets['Count'] ?? [], [
                    'max' => $value,
                ])
            };
        }
        $attributes = [];
        if (false === $modelProperty->isNullable && $modelProperty->type === 'string') {
            $attributes[] = 'Assert\\NotBlank';
        }

        foreach ($assets as $asset => $arguments) {
            $attributeString = "Assert\\$asset(";
            foreach ($arguments as $key => $value) {
                $attributeString .= "$key: $value,";
            }
            $attributeString .= ')';

            $attributes[] = $attributeString;
        }

        if ($property instanceof Reference) {
            $attributes[] = 'Assert\\Valid';
        } else {
            $additionalRules = $property->getExtensions();
            if (0 < count($additionalRules)) {
                foreach ($additionalRules as $propName => $rule) {
                    if (str_starts_with(strtolower($propName), 'x-')) { // ignore custom props
                        continue;
                    }
                    $attributes[] = 'Assert\\' . $rule;
                }
            }
        }

        if ($modelProperty->type === 'array') {
            $type = str_replace('[]', '', $modelProperty->docType);
            $type = str_replace(self::NULL_PREFIX, '', $type);
            $type = str_replace('.json', '', $type);
            //$type = str_replace('|', '', $type);
            $attributes[] = 'Assert\\Valid';
            // strip out null prefix since validator will not work correctly

            $attributes[] = 'Assert\All([
                new Assert\Type(' . $type . '::class)
            ])';
        }

        return $attributes;
    }

    private function getModelNameFromRef(string $ref): string
    {
        $refParts = explode('/', $ref);

        return end($refParts);
    }
}
