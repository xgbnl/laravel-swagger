<?php

namespace Xgbnl\LaravelSwagger\Attributes;

use Attribute;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class OAMultiPartForm extends OARequestBody
{
    #[Pure] public function __construct(array|string $schema, string $description = '')
    {
        parent::__construct($schema, $description, type: 'multipart/form-data');
    }
}
