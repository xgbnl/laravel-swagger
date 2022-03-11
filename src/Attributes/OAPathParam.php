<?php


namespace MaxShaw\LaravelSwagger\Attributes;


use Attribute;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OAPathParam extends OAParameter
{
    #[Pure]
    public function __construct(
        string  $name,
        string  $description = '',
        string  $type = 'string',
        array   $enum = [],
        ?string $example = null,
    )
    {
        parent::__construct('path', $name, $description, $type, true, $enum, $example);
    }
}
