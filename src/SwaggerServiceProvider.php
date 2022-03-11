<?php

namespace Xgbnl\LaravelSwagger;

use Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-swagger');

        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/laravel-swagger'),
        ], 'public');

        $this->publishes([
            __DIR__ . '/../config/laravel-swagger.php' => config_path('laravel-swagger.php'),
            __DIR__ . '/../resources/views'            => resource_path('views/vendor/laravel-swagger'),
        ]);
    }
}
