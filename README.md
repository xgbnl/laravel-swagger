# laravel Swagger

## install
```shell
composer require xgbnl/laravel-swagger
```
## mount service provider
```php
use Xgbnl\LaravelSwagger\SwaggerServiceProvider;

  public function register()
  {
        if ($this->app->isLocal()) {
            $this->app->register(SwaggerServiceProvider::class);
        }
  }
```

## publish
```shell
php artisan swagger:publish
```

## access
```shell
laravel.test/api/docs
```

## exampe

1. You must assigned app url.

```env
APP_URL=http://laravel.test
```


2. Login swagger api document debuge.
configuire `config/laravel-swagger.php`
```php
 'api_key'       => [
        'middlewares' => ['auth:api'], // [guard:user]
        // ...other config
    ],
```

3. Add parameters for your query method.

```php
use Xgbnl\LaravelSwagger\Attributes\OAQueryParam;

#[OATag('Goods List')]
#[OAQueryParam('id', 'Goods ID', 'integer', true, [1001 => 'id'], '?id=1001')]
#[OAQueryParam('name', 'Goods Name', 'string', true, ['Iphone14' => 'name'], '?name=Iphone14')]
public function index(): JsonResponse
{
    // do
}

```
5. Request class auto inject to your post or patch method
```php
 // UserRequest.php

public function store():JsonResponse
{
    // do
}

```