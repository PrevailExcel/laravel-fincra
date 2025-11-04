<?php

namespace PrevailExcel\Fincra;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class FincraServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/fincra.php' => config_path('fincra.php'),
            ], 'fincra-config');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'fincra');
        $this->registerRoutes();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/fincra.php', 'fincra');

        $this->app->bind('laravel-fincra', function () {
            return new Fincra();
        });
    }

    /**
     * Register routes.
     */
    protected function registerRoutes()
    {
        Route::macro('callback', function ($controller, $method = 'handleGatewayCallback') {
            return Route::get('/fincra/callback', [$controller, $method])
                ->name('fincra.callback');
        });

        Route::macro('webhook', function ($controller, $method = 'handleWebhook') {
            return Route::post('/fincra/webhook', [$controller, $method])
                ->name('fincra.webhook');
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laravel-fincra'];
    }
}