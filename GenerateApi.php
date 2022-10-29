<?php

namespace Dparadiz\Codegen;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class GenerateApi extends Command
{
    private const MODEL_NAMESPACE = 'Api\\Model';
    private const OPERATION_NAMESPACE = 'Api\\Operation';
    private const PARAMETER_NAMESPACE = 'Api\\Parameter';
    private const DESERIALIZER_NAMESPACE = 'Api\\Deserializer';

    protected function configure()
    {
        $defaultTemplatePath = realpath(__DIR__ . '/Resources/templates');

        $this->setDescription('Creates api operation handlers based on openapi specification and templates')
            ->addOption('open-api-spec', 's', InputOption::VALUE_REQUIRED, 'Specification json file')
            ->addOption('output-folder', 'o', InputOption::VALUE_REQUIRED, 'Output folder')
            ->addOption('ecs-config', 'e', InputOption::VALUE_REQUIRED, 'ecs config')
            ->addOption(
                'templates',
                't',
                InputOption::VALUE_REQUIRED,
                'Mustache template for rendering',
                $defaultTemplatePath
            )
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace. Default Api', 'Api');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $codeRenderer = RendererFactory::getRenderer(
            (string)$input->getOption('templates'),
            $input->getOption('namespace')
        );

        $openApiSpec = Reader::readFromYamlFile(realpath($input->getOption('open-api-spec')), OpenApi::class, false);

        $stack = new Stack();
        $routeTemplateData = [
            'routes' => [],
            'services' => ['JsonSerializer'],
        ];

        foreach ($openApiSpec->paths as $uri => $path) {
            if ($path->get instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'get',
                    'operationId' => $path->get->operationId,
                    'handler' => 'api_' . $path->get->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->get, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['services'] = array_merge($routeTemplateData['services'], $services);
            }

            if ($path->post instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'post',
                    'operationId' => $path->post->operationId,
                    'handler' => 'api_' . $path->post->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->post, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['services'] = array_merge($routeTemplateData['services'], $services);
            }

            if ($path->delete instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'delete',
                    'operationId' => $path->delete->operationId,
                    'handler' => 'api_' . $path->delete->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->delete, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['services'] = array_merge($routeTemplateData['services'], $services);
            }

            if ($path->patch instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'patch',
                    'operationId' => $path->patch->operationId,
                    'handler' => 'api_' . $path->patch->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->patch, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['services'] = array_merge($routeTemplateData['services'], $services);
            }

            if ($path->put instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'put',
                    'operationId' => $path->put->operationId,
                    'handler' => 'api_' . $path->put->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->put, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['services'] = array_merge($routeTemplateData['services'], $services);
            }

            if ($path->options instanceof Operation) {
                $route = [
                    'path' => $uri,
                    'method' => 'options',
                    'operationId' => $path->options->operationId,
                    'handler' => 'api_' . $path->options->operationId . '_handler',
                ];
                $services = [];
                $this->processOperation($path->options, $stack, $route, $services);
                $routeTemplateData['routes'][] = $route;
                $routeTemplateData['routes'] = array_merge($routeTemplateData['routes'], $services);
            }
        }
        $routeTemplateData['services'] = array_unique($routeTemplateData['services']);

        $stack->push(new StackItem('di', '../config/di/api_base', $routeTemplateData));
        $stack->push(new StackItem('routes', '../config/routes/api', $routeTemplateData));


        $outputFolder = $input->getOption('output-folder');
        $codeRenderer->process($stack, $outputFolder, (string)$input->getOption('namespace'));

        //try {
        //    $this->runEcs($outputFolder, $input->getOption('ecs-config'));
        //} catch (RuntimeException $e) {
        //    $output->writeln('Ecs failed');
        //    $output->write($e->getMessage());
        //
        //    return 1;
        //}

        $output->writeln('Code generation complete');
        return Command::SUCCESS;
    }

    private function processOperation(Operation $operation, Stack $stack, array &$route, array &$services): void
    {
        $operationClassName = $operation->operationId;
        $templateData = [
            'namespace' => self::OPERATION_NAMESPACE,
            'className' => $operationClassName,
            'imports' => [],
        ];
        $route['operation'] = self::OPERATION_NAMESPACE . '\\' . $operationClassName;
        $route['serializer'] = 'JsonSerializer';

        $hasDeserializer = false;
        $deserializerData = [
            'imports' => [],
            'className' => $operationClassName,
            'namespace' => self::DESERIALIZER_NAMESPACE,
        ];

        $requestParams = $this->processParameters($operation, $stack, $templateData, $route['path']);

        if ($requestParams !== []) {
            $hasDeserializer = true;
            $deserializerData['imports'][] = $requestParams['import'];
            $deserializerData['requestParams'] = $requestParams['className'];
        }
        $requestBody = $this->processRequestBody($operation, $stack, $templateData);


        if ($requestBody !== []) {
            $hasDeserializer = true;
            $deserializerData['imports'][] = $requestBody['import'];
            $deserializerData['requestBody'] = $requestBody['className'];
        }

        if ($hasDeserializer) {
            $stack->push(
                new StackItem(
                    'deserializer',
                    self::DESERIALIZER_NAMESPACE . "\\{$operation->operationId}",
                    $deserializerData
                )
            );
            $services[] = self::DESERIALIZER_NAMESPACE . "\\{$operation->operationId}";
            $route['deserializer'] = self::DESERIALIZER_NAMESPACE . "\\{$operation->operationId}";
        } else {
            $route['deserializer'] = "EmptyDeserializer";
        }
        // generate response models
        $response = $operation->responses->getResponse(201) ?? $operation->responses->getResponse(200);

        if (isset($response->content['application/json'])) {
            $tmp = explode('/', $response->content['application/json']->schema->getReference());
            $modelName = end($tmp);

            $schema = $response->content['application/json']->schema->resolve();
            $stackItem = new StackItem('model', self::MODEL_NAMESPACE . '\\' . $modelName);
            $stackItem->templateData = $this->prepareModelData($modelName, $schema, $stack);
            $stack->push($stackItem);

            $templateData['imports'][] = self::MODEL_NAMESPACE;
            $templateData['responseType'] = self::MODEL_NAMESPACE . '\\' . $modelName;
        }

        $stackItem = new StackItem('operation', self::OPERATION_NAMESPACE . '\\' . $operationClassName);
        $templateData['imports'] = array_unique($templateData['imports']);
        $stackItem->templateData = $templateData;
        $stack->push($stackItem);
    }

    private function processRequestBody(Operation $operation, Stack $stack, array &$templateData): array
    {
        if (!isset($operation->requestBody)) {
            return [];
        }

        $schema = $operation->requestBody->content['application/json']->schema;

        if (!($schema instanceof Reference)) {
            throw new \RuntimeException('Request body should be reference');
        }

        $tmp = explode('/', $schema->getReference());
        $modelName = end($tmp);

        $className = self::MODEL_NAMESPACE . '\\' . $modelName;
        $templateData['requestBody'] = [
            'type' => $className,
            'name' => lcfirst($modelName),
        ];

        $templateData['imports'][] = self::MODEL_NAMESPACE;

        $schema = $schema->resolve();
        $stackItem = new StackItem('model', $className);
        $stackItem->templateData = $this->prepareModelData($modelName, $schema, $stack);
        $stack->push($stackItem);

        return [
            'className' => $className,
            'import' => self::MODEL_NAMESPACE,
        ];
    }

    private function processParameters(Operation $operation, Stack $stack, array &$templateData, string $path): array
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
                $stackItem = new StackItem('enum', self::MODEL_NAMESPACE . '\\' . $type);
                $isString = $parameter->schema->type === 'string';
                $stackItem->templateData = [
                    'className' => substr($type, 5),
                    'namespace' => self::MODEL_NAMESPACE . '\\Enum',
                    'isString' => $isString,
                    'type' => $isString ? 'string' : 'int',
                    'enums' => array_map(fn($value) => [
                        'value' => $value,
                        'name' => ucfirst($value),
                    ], $parameter->schema->enum),
                ];
                $stack->push($stackItem);
                $importEnum = true;
            }

            // Prepare validation rules
            $propertyDataMapper = new PropertyDataMapper();
            $modelProperty = $propertyDataMapper->getProperty($parameter->name, $parameter->schema, $parameter->required);

            $parameters[] = [
                'name' => $parameter->name,
                'docType' => $type === 'array' ? $typeResolver->getArrayDocType($parameter->schema) : $type,
                'type' => $type,
                'required' => $parameter->required,
                'inQuery' => $parameter->in === 'query',
                'inPath' => $parameter->in === 'path',
                'useEnum' => str_starts_with($type, 'Enum\\'),
                'attributes' => $modelProperty->attributes,
            ];

            if ($parameter->in === 'path') {
                $pathParameters[] = $parameter;
            }
        }


        if ($parameters === []) {
            return [];
        }

        $parameterClassName = self::PARAMETER_NAMESPACE . "\\{$operation->operationId}";
        $templateData['requestParams'] = [
            'type' => $parameterClassName,
            'name' => 'requestParams',
        ];
        $templateData['imports'][] = self::PARAMETER_NAMESPACE;

        $stackItem = new StackItem('parameters', $parameterClassName);
        $stackItem->templateData = [
            'namespace' => self::PARAMETER_NAMESPACE,
            'className' => $operation->operationId,
            'parameters' => $parameters,
            'regexMatch' => $this->pathToRegex($path, $pathParameters),
        ];
        if ($importEnum) {
            $stackItem->templateData['imports'] = ['Model\Enum'];
        }
        $stack->push($stackItem);

        return [
            'import' => self::PARAMETER_NAMESPACE,
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
                || !in_array($parameter->schema->type, ['string', 'integer'])
            ) {
                throw new \RuntimeException('Path parameter can only be string or integer');
            }
            $regexMatch = '(?P<' . $parameter->name . '>';
            $regexMatch .= $parameter->schema->type === 'integer' ? '\d' : '[^/]';
            $regexMatch .= $parameter->required ? '+)' : '*)';
            $path = str_replace(sprintf('{%s}', $parameter->name), $regexMatch, $path);
        }


        return '~.*' . $path . '~';
    }

    #[ArrayShape([
        'imports' => "array",
        'properties' => "array",
        'constants' => "array",
        'namespace' => "string",
        'className' => "string",
    ])]
    private function prepareModelData(string $modelName, Schema $schema, Stack $stack): array
    {
        $data = [
            'imports' => [],
            'properties' => [],
            'constants' => [],
            'namespace' => self::MODEL_NAMESPACE,
            'className' => $modelName,
            'attributes' => [],
        ];

        if (isset($schema->{'x-constraint'})) {
            $data['attributes'][] = $schema->{'x-constraint'};
            $data['imports'][] = 'Validator as ApiConstrain';
        }

        if ($schema->type === 'object') {
            $required = array_intersect_key($schema->properties, array_flip($schema->required ?? []));

            foreach ($required as $pName => $property) {
                $this->populateModelData($data, $pName, $property, $schema, $stack);
            }

            $optional = array_diff_key($schema->properties, array_flip($schema->required ?? []));

            foreach ($optional as $pName => $property) {
                $this->populateModelData($data, $pName, $property, $schema, $stack);
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
                    $this->populateModelData($data, $pName, $property, $schema, $stack);
                }

                foreach ($optional as $allOfPName => $allOfProperty) {
                    $this->populateModelData($data, $allOfPName, $allOfProperty, $schema, $stack);
                }
            }
        }

        return $data;
    }


    private function populateModelData(array &$data, string $pName, Schema|Reference $property, Schema $schema, Stack $stack): void
    {
        $propertyDataMapper = new PropertyDataMapper();
        $isRequired = in_array($pName, $schema->required ?? []);
        $modelProperty = $propertyDataMapper->getProperty($pName, $property, $isRequired);

        // add property models to stack
        if ($property instanceof Reference) {
            $name = $this->getModelNameFromRef($property->getReference());
            $property = $property->resolve();
            if ($property instanceof Schema) {
                $stackItem = new StackItem('model', self::MODEL_NAMESPACE . '\\' . $name);
                $stackItem->templateData = $this->prepareModelData($name, $property, $stack);
                $stack->push($stackItem);
            }
        }

        // add array item
        if ($property->type === 'array') {
            if ($property->items instanceof Reference) {
                $name = $this->getModelNameFromRef($property->items->getReference());
                $item = $property->items->resolve();

                if ($item instanceof Schema) {
                    $stackItem = new StackItem('model', self::MODEL_NAMESPACE . '\\' . $name);
                    $stackItem->templateData = $this->prepareModelData($name, $item, $stack);
                    $stack->push($stackItem);
                }
            }
        }

        if (!empty($property->enum)) {
            $enumName = ucfirst($pName);

            $stackItem = new StackItem('enum', self::MODEL_NAMESPACE . '\\Enum\\' . $enumName);
            $isString = $property->type === 'string';
            $stackItem->templateData = [
                'className' => $enumName,
                'namespace' => self::MODEL_NAMESPACE . '\\Enum',
                'isString' => $isString,
                'type' => $isString ? 'string' : 'int',
                'enums' => array_map(fn($value) => [
                    'value' => $value,
                    'name' => ucfirst($value),
                ], $property->enum),
            ];
            $stack->push($stackItem);
        }

        if (!empty($property->oneOf)) {
            foreach ($property->oneOf as $oneOfSchema) {
                $name = $this->getModelNameFromRef($oneOfSchema->getReference());

                $oneOfSchema = $oneOfSchema->resolve();

                if ($oneOfSchema instanceof Schema) {
                    $stackItem = new StackItem('model', self::MODEL_NAMESPACE . '\\' . $name);
                    $stackItem->templateData = $this->prepareModelData($name, $oneOfSchema, $stack);
                    $stack->push($stackItem);
                }
            }
        }

        $data['properties'][] = get_object_vars($modelProperty);
    }


    private function getModelNameFromRef(string $ref): string
    {
        $refParts = explode('/', $ref);

        return end($refParts);
    }
}
