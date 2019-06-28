# InWeb Admin

Admin Panel for Laravel Framework

## Features
- CRUD
- Images management

## Requirements
- Laravel >=5.6

## Installation

1. Install package via *composer require*
    ```
    composer require inweb/admin
    ```
    or add to your composer.json to **require** section and update your dependencies
    ```
    "inweb/admin": "*"
    ```
2. Run migrations
    ```
    php artisan migrate
    ```
3. Run seeder

    Run seeder to create admin user with default credentials
    ```
    php artisan db:seed --class="InWeb\Admin\Database\Seeds\DatabaseSeeder"
    ```
4. Add guard in **config/auth.php**
```php
'guards' => [
     ...

    'admin' => [
        'driver' => 'session',
        'provider' => 'admin',
    ],
],
```
5. Add provider in **config/auth.php**
    ```php
    'providers' => [
         ...

         'admin' => [
             'driver' => 'eloquent',
             'model' => InWeb\Admin\App\Models\AdminUser::class,
         ],
    ],
    ```
6. Publish assets
    ```
    php artisan vendor:publish --provider="InWeb\Admin\App\Providers\AdminServiceProvider" --tag=public
    ```
    
You are ready to go!
