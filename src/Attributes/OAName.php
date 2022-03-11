<?php

namespace MaxShaw\LaravelSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OAName
{
    public function __construct(public string $summary = '', public string $description = '')
    {
    }
}
