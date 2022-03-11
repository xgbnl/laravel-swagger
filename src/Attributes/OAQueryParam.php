<?php


namespace Xgbnl\LaravelSwagger\Attributes;


use Attribute;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OAQueryParam extends OAParameter
{
    #[Pure]
    public function __construct(
        string  $name,
        string  $description = '',
        string  $type = 'string',
        bool    $required = false,
        array   $enum = [],
        ?string $example = null,
    )
    {
        parent::__construct('query', $name, $description, $type, $required, $enum, $example);
    }
}
