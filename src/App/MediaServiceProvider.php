<?php

namespace InWeb\Media;

use Illuminate\Database\Eloquent\Factory as EloquentFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InWeb\Media\Console\Commands\CreateExtraFormats;
use InWeb\Media\Console\Commands\SetMissingFormat;

class MediaServiceProvider extends ServiceProvider
{
    protected static $packagePath  = __DIR__ . '/../../';
    protected static $packageAlias = 'media';

    public static function getPackageAlias()
    {
        return self::$packageAlias;
    }

    public static function getPackagePath()
    {
        return self::$packagePath;
    }

    /**
     * Bootstrap any package services.
     *
     * @param Router $router
     * @return void
     */
    public function boot(Router $router)
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }

        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(
                static::getPackagePath() . 'src/config/config.php',
                static::getPackageAlias()
            );
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function register()
    {
        $this->registerResources();
        $this->registerCommands();
    }

    /**
     * Register the package resources such as routes, templates, etc.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function registerResources()
    {
        $this->loadMigrationsFrom(self::$packagePath . 'src/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->app->make(EloquentFactory::class)->load(self::$packagePath . 'src/database/factories');
        }
    }

    private function registerPublishing()
    {
        // Config
        $this->publishes([
            self::$packagePath . 'src/config/config.php' => config_path(self::$packageAlias . '.php'),
        ], 'config');
    }

    private function registerCommands()
    {
        $this->commands([
            SetMissingFormat::class,
            CreateExtraFormats::class,
        ]);
    }
}
