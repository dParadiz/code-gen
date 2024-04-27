<?php declare(strict_types=1);

namespace Dparadiz\Codegen;

use cebe\openapi\SpecObjectInterface;
use Dparadiz\Codegen\Generator\Stack;

interface EncoderInterface
{
    public function encode(SpecObjectInterface $openApiSpec): Stack;
}