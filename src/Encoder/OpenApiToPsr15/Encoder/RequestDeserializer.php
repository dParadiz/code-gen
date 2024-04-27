<?php declare(strict_types=1);

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\Encoder;

use cebe\openapi\spec\Operation;
use Dparadiz\Codegen\Generator\Stack;
use Dparadiz\Codegen\Generator\StackItem;

class RequestDeserializer
{

    private RequestParam $requestParamEncoder;
    private RequestBody $requestBodyEncoder;

    public function __construct(
        private string $deserializerNamespace,
        private string $modelNamespace,
        private string $parameterNamespace,
    )
    {
        $this->requestParamEncoder = new RequestParam($this->modelNamespace, $this->parameterNamespace);
        $this->requestBodyEncoder = new RequestBody($this->modelNamespace);
    }

    public function encode(Operation $operation, Stack $stack, array &$templateData, string $path): ?string
    {
        $className = new OperationClassName($operation);

        if ($className === '') {
            $className = implode("", array_map(
                fn(string $value) => ucfirst(preg_replace('/[^a-zA-Z]/', '', $value)),
                array_filter(explode("/", $path))
            ));
        }

        $hasDeserializer = false;
        $deserializerData = [
            'imports' => [],
            'className' => $className,
            'namespace' => $this->deserializerNamespace,
        ];

        $requestParams = $this->requestParamEncoder->encode($operation, $stack, $templateData, $path);

        if ($requestParams !== []) {
            $hasDeserializer = true;
            $deserializerData['imports'][] = $requestParams['import'];
            $deserializerData['requestParams'] = str_replace('.json', '', $requestParams['className']);
        }

        $requestBody = $this->requestBodyEncoder->encode($operation, $stack, $templateData);


        if ($requestBody !== []) {
            $hasDeserializer = true;
            $deserializerData['imports'][] = $requestBody['import'];
            $deserializerData['requestBody'] = str_replace('.json', '', $requestBody['className']);
        }


        if ($hasDeserializer) {
            $stack->push(
                new StackItem(
                    'deserializer',
                    $this->deserializerNamespace . "\\{$className}",
                    $deserializerData
                )
            );
            return $this->deserializerNamespace . "\\{$className}";
        }

        return null;
    }

}