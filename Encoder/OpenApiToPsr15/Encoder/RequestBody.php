<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\Encoder;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use Dparadiz\Codegen\Generator\Stack;
use Dparadiz\Codegen\Generator\StackItem;

class RequestBody
{

    public function __construct(
        private readonly string $modelNamespace,
    )
    {

    }

    public function encode(Operation $operation, Stack $stack, array &$templateData): array
    {
        if (!isset($operation->requestBody)) {
            return [];
        }

        if (!isset($operation->requestBody->content['application/json'])) {
            echo "Request body content not set on : {$operation->summary}\n";
            return [];
        }

        $schema = $operation->requestBody->content['application/json']->schema;


        if ($schema instanceof Reference) {
            $tmp = explode('/', $schema->getReference());
            $modelName = str_replace('.json', '', end($tmp));

            $className = $this->modelNamespace . '\\' . $modelName;
            $templateData['requestBody'] = [
                'type' => $className,
                'name' => lcfirst($modelName),
            ];

            $templateData['imports'][] = $this->modelNamespace;

            $schema = $schema->resolve();

            (new Model($this->modelNamespace))->encode($modelName, $schema, $stack);

            return [
                'className' => $className,
                'import' => $this->modelNamespace,
            ];
        } elseif (($schema->type ?? '') === 'array' && $schema->items instanceof Reference) {
            $tmp = explode('/', $schema->items->getReference());
            $modelName = end($tmp);

            //$className = $this->modelNamespace . '\\' . $modelName;
            $templateData['requestBody'] = [
                'type' => 'array',
                'name' => 'array',
            ];

            $templateData['imports'][] = $this->modelNamespace;

            $schema = $schema->items->resolve();

            (new Model($this->modelNamespace))->encode($modelName, $schema, $stack);
            return [
                'className' => 'array',
                'import' => '',
            ];

        }

        throw new \RuntimeException("Request body should be array of referenced items or reference");
    }
}