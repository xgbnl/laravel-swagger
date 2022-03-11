<?php

use Illuminate\Support\Facades\Route;
use MaxShaw\LaravelSwagger\OperationBuilder;

if (defined('API_KEY_MIDDLEWARES')) {
    return;
}

define('API_KEY_MIDDLEWARES', config('laravel-swagger.api_key.middlewares') ?: []);
define('API_KEY_MIDDLEWARES_DEFINED', !empty(API_KEY_MIDDLEWARES));

Route::prefix('/api/docs')->group(function () {
    Route::get('/', function () {
        return view('vendor.laravel-swagger.index');
    });

    Route::get('/openapi', function () {
        $baseResponseSchema = config('laravel-swagger.response.schema');

        $schemas = [];

        if ($baseResponseSchema) {
            $baseResponseDataKey     = config('laravel-swagger.response.data_key');
            $schemas['BaseResponse'] = [
                'title'      => '通用',
                'type'       => 'object',
                'properties' => $baseResponseSchema,
            ];
        } else {
            $baseResponseDataKey = null;
        }

        $excludedUris = config('laravel-swagger.excluded_uris', []);

        $isEmptyExcludedUrls = empty($excludedUris);

        $useRestful = config('laravel-swagger.restful', false);

        $operations = [];

        $tags = [];

        foreach ($this->app->router->getRoutes() as $route) {
            /* @var \Illuminate\Routing\Route $route */
            $middlewares = $route->gatherMiddleware();

            if (!in_array('api', $middlewares)) {
                continue;
            }

            if (!$isEmptyExcludedUrls) {
                $routeUri = ltrim($route->uri, '/');

                foreach ($excludedUris as $excludedUri) {
                    $excludedUri = ltrim($excludedUri, '/');
                    if (!empty($excludedUri)) {
                        if (str_ends_with($excludedUri, '*') && str_starts_with($routeUri, rtrim($excludedUri, '*'))) {
                            continue 2;
                        } else if ($excludedUri === $routeUri) {
                            continue 2;
                        }
                    }
                }
            }

            $method = strtolower(array_filter($route->methods, fn($s) => $s !== 'HEAD')[0]);

            $action = $route->getActionName();

            if ($action === 'Closure') {
                $builder = new OperationBuilder($method, $tags, $schemas, $route->getAction('uses'), $route->parameterNames());
            } else {
                $builder = new OperationBuilder($method, $tags, $schemas, explode('@', $action), $route->parameterNames());
            }

            $operations[substr($route->uri, 3)][$method] = $builder
                ->withRestful($useRestful)
                ->withSecurity($middlewares, API_KEY_MIDDLEWARES)
                ->withBaseResponse($baseResponseDataKey)
                ->build();

            $tags    = $builder->tags;
            $schemas = $builder->schemas;
        }

        $apiKeyDef = config('laravel-swagger.api_key.definition');

        return response()->json([
                'openapi'    => '3.0.1',
                'info'       => config('laravel-swagger.info') ?: [],
                'servers'    => config('laravel-swagger.servers') ?? [],
                'tags'       => array_values($tags),
                'paths'      => $operations,
                'components' => [
                        'schemas' => $schemas,
                    ] + ($apiKeyDef ? ['securitySchemes' => [
                        'api_key' => $apiKeyDef,
                    ]] : []),
            ] + (config('laravel-swagger.https_enabled') ? ['schemes' => ['http', 'https']] : []));
    });
});
