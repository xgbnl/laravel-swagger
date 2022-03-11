<?php


namespace MaxShaw\LaravelSwagger\Attributes;


use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OATag
{
    public function __construct(public string $name, public string $description = '')
    {
    }
}
