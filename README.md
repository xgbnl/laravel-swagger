# laravel 接口文档工具

## install
```shell
composer require xgbnl/laravel-swagger
```

## publish
```shell
php artisan publish:swagger
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

4. Add parameters for your patch or post method.
```php
use Xgbnl\LaravelSwagger\Attributes\OAQueryParam;

#[OATag('Create Goods')]
#[OAPathParam('name','Goods Name')]
public function store(): JsonResponse
{
    // do
}

```

5. Add parameters for your upload method.
```php
#[OAMultiPartForm('cover','Goods Cover Image')]
public function upload(): JsonResponse
{
    // do
}