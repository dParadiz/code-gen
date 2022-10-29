<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\Encoder;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Reference;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\Enum;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\EnumItem;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\PathParameter;
use Dparadiz\Codegen\Generator\Stack;
use Dparadiz\Codegen\Generator\StackItem;
use Dparadiz\Codegen\PropertyDataMapper;
use Dparadiz\Codegen\TypeResolver;

class RequestParam
{

    public function __construct(
        private readonly string $modelNamespace,
        private readonly string $parameterNamespace,
    )
    {

    }

    public function encode(Operation $operation, Stack $stack, array &$templateData, string $path): array
    {
        $parameters = [];
        $pathParameters = [];
        $importEnum = false;
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Reference) {
                $parameter = $parameter->resolve();
            }

            if (!($parameter instanceof Parameter)) {
                throw new \RuntimeException('Invalid parameter type');
            }

            $typeResolver = new TypeResolver();
            $type = $typeResolver->getType($parameter->schema, $parameter->name);

            if (!empty($parameter->schema->enum)) {
                $stackItem = new StackItem('enum', $this->modelNamespace . '\\' . $type);
                $isString = $parameter->schema->type === 'string';
                $stackItem->templateData = new Enum(
                    className: ucfirst(substr($type, 5)),
                    namespace: $this->modelNamespace . '\\Enum',
                    isString: $isString,
                    type: $isString ? 'string' : 'int',
                    enums: array_map(fn($value) => new EnumItem(ucfirst($value), $value), $parameter->schema->enum)
                );

                $stack->push($stackItem);
                $importEnum = true;
            }

            // Prepare validation rules
            $propertyDataMapper = new PropertyDataMapper();
            $modelProperty = $propertyDataMapper->getProperty($parameter->name, $parameter->schema, $parameter->required);

            $parameters[] = new PathParameter(
                name: $parameter->name,
                docType: $type === 'array' ? $typeResolver->getArrayDocType($parameter->schema) : $type,
                type: $type,
                required: $parameter->required,
                inQuery: $parameter->in === 'query',
                inPath: $parameter->in === 'path',
                useEnum: str_starts_with($type, 'Enum\\'),
                attributes: $modelProperty->attributes,
            );


            if ($parameter->in === 'path') {
                $pathParameters[] = $parameter;
            }
        }


        if ($parameters === []) {
            return [];
        }

        $className = ucfirst($operation->operationId);
        $parameterClassName = $this->parameterNamespace . "\\{$className}";
        $templateData['requestParams'] = [
            'type' => $parameterClassName,
            'name' => 'requestParams',
        ];
        $templateData['imports'][] = $this->parameterNamespace;

        $stackItem = new StackItem('parameters', $parameterClassName);
        $stackItem->templateData = [
            'namespace' => $this->parameterNamespace,
            'className' => $className,
            'parameters' => $parameters,
            'regexMatch' => $this->pathToRegex($path, $pathParameters),
        ];

        if ($importEnum) {
            $stackItem->templateData['imports'] = ['Model\Enum'];
        }
        $stack->push($stackItem);

        return [
            'import' => $this->parameterNamespace,
            'className' => $parameterClassName,
        ];
    }

    private function pathToRegex(string $path, array $parameters): ?string
    {
        if ($parameters === []) {
            return null;
        }

        foreach ($parameters as $parameter) {
            if (!($parameter instanceof Parameter)
                || $parameter->schema->type instanceof Reference
                || !in_array($parameter->schema->type, ['string', 'integer', 'int'])
            ) {
                echo "Path parameter can only be string or integer got {$parameter->schema->type}\n";
                continue;
                //throw new \RuntimeException('Path parameter can only be string or integer got ' . $parameter->schema->type);
            }
            $regexMatch = '(?P<' . $parameter->name . '>';
            $regexMatch .= in_array($parameter->schema->type, ['integer', 'int']) ? '\d' : '[^/]';
            $regexMatch .= $parameter->required ? '+)' : '*)';
            $path = str_replace(sprintf('{%s}', $parameter->name), $regexMatch, $path);
        }


        return '~.*' . $path . '~';
    }
}