<?php

namespace InWeb\Media;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use InWeb\Media\Console\Commands\CreateExtraFormats;
use InWeb\Media\Console\Commands\SetMissingFormat;
use InWeb\Media\Database\Factories\TestEntityFactory;

class MediaServiceProvider extends ServiceProvider
{
    protected static string $packagePath  = __DIR__ . '/../../';
    protected static string $packageAlias = 'media';

    public static function getPackageAlias(): string
    {
        return self::$packageAlias;
    }

    public static function getPackagePath(): string
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
