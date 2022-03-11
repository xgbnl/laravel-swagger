<?php

namespace Xgbnl\LaravelSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OAResponseBody
{
    public function __construct(public array|string $schemaOrRef, public string $type = 'application/json')
    {
    }
}
