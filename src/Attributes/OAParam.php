<?php

namespace MaxShaw\LaravelSwagger\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_PARAMETER)]
class OAParam
{
    public function __construct(public string $description, public bool $required = true, public ?string $example = null)
    {
    }

    public static function of(string $description, string $type = 'string') {
        return [];
    }
}
