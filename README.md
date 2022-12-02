# laravel Swagger

## install
```shell
composer require xgbnl/laravel-swagger
```
## mount service

Edit `App\Providers\AppServiceProvider`

```php
use Xgbnl\LaravelSwagger\SwaggerServiceProvider;

  public function register()
  {
    // allow local env access
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

You must assigned app url.

```env
APP_URL=http://laravel.test
```