<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\SpecObjectInterface;
use Dparadiz\Codegen\Encoder\OpenApiToPsr15\TemplateData\PathParameter;
use Dparadiz\Codegen\EncoderInterface;
use Dparadiz\Codegen\Generator\Stack;
use Dparadiz\Codegen\Generator\StackItem;

class Encoder implements EncoderInterface
{
    private const MODEL_NAMESPACE = 'Model';
    private const OPERATION_NAMESPACE = 'Operation';
    private const PARAMETER_NAMESPACE = 'Parameter';
    private const DESERIALIZER_NAMESPACE = 'Deserializer';


    private Encoder\Model $modelEncoder;
    private Encoder\RequestDeserializer $requestDeserializer;


    public function encode(SpecObjectInterface $openApiSpec): Stack
    {

        $this->modelEncoder = new Encoder\Model(self::MODEL_NAMESPACE);
        $this->requestDeserializer = new Encoder\RequestDeserializer(
            self::DESERIALIZER_NAMESPACE,
            self::MODEL_NAMESPACE,
            self::PARAMETER_NAMESPACE
        );

        $stack = new Stack();

        $routeTemplateData = [
            'routes' => [],
            'services' => [],
            'operations' => [],
        ];

        foreach ($openApiSpec->paths as $uri => $path) {
            if ($path->get instanceof Operation) {
                $this->processOperation($path->get, $stack, $uri, $routeTemplateData, 'get');
            }

            if ($path->post instanceof Operation) {
                $this->processOperation($path->post, $stack, $uri, $routeTemplateData, 'post');
            }

            if ($path->delete instanceof Operation) {
                $this->processOperation($path->delete, $stack, $uri, $routeTemplateData, 'delete');
            }

            if ($path->patch instanceof Operation) {
                $this->processOperation($path->patch, $stack, $uri, $routeTemplateData, 'patch');
            }

            if ($path->put instanceof Operation) {
                $this->processOperation($path->put, $stack, $uri, $routeTemplateData, 'put');
            }

            if ($path->options instanceof Operation) {
                $this->processOperation($path->options, $stack, $uri, $routeTemplateData, 'optons');
            }
        }

        $routeTemplateData['services'] = array_values(array_unique($routeTemplateData['services']));

        $stack->push(new StackItem('di', '../../config/di/api_base', $routeTemplateData));
        $stack->push(new StackItem('implementation', '../../config/di/template_api', $routeTemplateData));
        $stack->push(new StackItem('routes', '../../config/routes/api', $routeTemplateData));

        return $stack;
    }

    private function processOperation(Operation $operation, Stack $stack, string $path, array &$routeTemplateData, string $method): void
    {
        $operationClassName = ucfirst($operation->operationId ?? '');

        if ($operationClassName === '') {
            echo "Missing operation id. Getting it from path $path {$operation->summary}\n";

            $operationClassName = implode("", array_map(
                fn(string $value) => ucfirst(preg_replace('/[^a-zA-Z]/', '', $value)),
                array_filter(explode("/", $path))
            ));
        }

        if (empty($operationClassName)) {
            throw new \RuntimeException('Operation id is not set');
        }

        $route = [
            'path' => $path,
            'method' => $method,
            'operationId' => $operationClassName,
            'handler' => 'api_' . $operationClassName . '_handler',
            'operation' => self::OPERATION_NAMESPACE . '\\' . $operationClassName,
        ];

        $templateData = [
            'namespace' => self::OPERATION_NAMESPACE,
            'className' => $operationClassName,

            'imports' => [],
        ];

        $deserializer = $this->requestDeserializer->encode($operation, $stack, $templateData, $path);

        if ($deserializer !== null) {
            $route['deserializer'] = $deserializer;
            $routeTemplateData['services'] = array_merge($routeTemplateData['services'], [$deserializer]);
        }
        $routeTemplateData['routes'][] = $route;

        // generate response models
        $response = $operation->responses->getResponse(201) ?? $operation->responses->getResponse(200);


        if (isset($response->content['application/json'])) {

            if ($response->content['application/json']->schema instanceof Reference) {
                $tmp = explode('/', $response->content['application/json']->schema->getReference());
                $modelName = end($tmp);

                $schema = $response->content['application/json']->schema->resolve();
                $this->modelEncoder->encode($modelName, $schema, $stack);

                $templateData['imports'][] = self::MODEL_NAMESPACE;
                $templateData['responseType'] = self::MODEL_NAMESPACE . '\\' . str_replace('.json', '', $modelName);
                $templateData['imports'][] = self::PARAMETER_NAMESPACE;

            } elseif ($response->content['application/json']->schema?->type === 'array') {
                if ($response->content['application/json']->schema->items instanceof Reference) {

                    $tmp = explode('/', $response->content['application/json']->schema->items->getReference());
                    $modelName = end($tmp);

                    $schema = $response->content['application/json']->schema->items->resolve();

                    $this->modelEncoder->encode($modelName, $schema, $stack);

                    $templateData['imports'][] = self::MODEL_NAMESPACE;
                    // TODO array doc type
                    $templateData['responseType'] = 'array';
                }
            } else {
                echo "Response must be json with reference schema or array.  $path \n";
            }
        }



        $stackItem = new StackItem('operation', self::OPERATION_NAMESPACE . '\\' . $operationClassName);
        $templateData['imports'] = array_unique($templateData['imports']);
        $stackItem->templateData = $templateData;
        $stack->push($stackItem);

        $routeTemplateData['operations'][] = $templateData;


    }
}