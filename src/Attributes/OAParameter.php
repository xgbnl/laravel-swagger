<?php


namespace MaxShaw\LaravelSwagger\Attributes;


abstract class OAParameter
{
    public function __construct(
        public string  $in,
        public string  $name,
        public string  $description = '',
        public string  $type = 'string',
        public bool    $required = false,
        public array   $enum = [],
        public ?string $example = null,
    )
    {
    }
}
