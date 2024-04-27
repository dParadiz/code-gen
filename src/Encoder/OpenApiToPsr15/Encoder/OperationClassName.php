<?php

namespace Dparadiz\Codegen\Encoder\OpenApiToPsr15\Encoder;

use cebe\openapi\spec\Operation;

final class OperationClassName 
{

    public readonly string $value;

    public function __construct(Operation $operation) {
        $this->value = str_replace(' ', '', ucwords($operation->operationId ?? ''));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}