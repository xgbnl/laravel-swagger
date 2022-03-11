<?php

namespace MaxShaw\LaravelSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OARequestBody
{
    public function __construct(public array|string $schema, public string $description = '', public string $type = 'application/json')
    {
    }
}
