<?php

declare(strict_types=1);

namespace Xgbnl\LaravelSwagger;

use Illuminate\Support\Facades\Facade;

class SwaggerFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'swagger';
    }
}
