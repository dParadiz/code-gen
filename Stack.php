<?php

namespace Dparadiz\Codegen;

final class Stack
{
    private array $data = [];

    public function push(StackItem $item): void
    {
        $this->data[] = $item;
    }

    public function pop(): StackItem
    {
        return array_pop($this->data);
    }

    public function peek(): StackItem
    {
        return end($this->data);
    }

    public function isEmpty(): bool
    {
        return $this->data === [];
    }
}
