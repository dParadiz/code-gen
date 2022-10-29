<?php

namespace Dparadiz\Codegen;

final class ModelProperty
{
    public string $name;
    public string $type;
    public string $docType;
    public bool $isNullable = false;
    public ?string $defaultValue = null;
    public array $attributes = [];

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
        $this->docType = $type;
    }
}