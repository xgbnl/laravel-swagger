<?php


namespace Xgbnl\LaravelSwagger\Attributes;


use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OASchema
{
    public function __construct(public array $schema, public string $type = 'object')
    {
    }
}
