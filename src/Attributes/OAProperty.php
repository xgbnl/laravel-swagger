<?php


namespace MaxShaw\LaravelSwagger\Attributes;


use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class OAProperty
{
    public function __construct(public string $name, public string $description = '', public string $type = 'string', public array $enum = [], public ?string $example = null)
    {
    }
}
