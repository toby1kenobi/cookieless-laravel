<?php

namespace App\Providers;

use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Session\SessionServiceProvider as OriginalServiceProvider;
use App\Http\Middleware\StartSession;

class SessionServiceProvider extends OriginalServiceProvider {
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->singleton(StartSession::class, function ($app) {
            return new StartSession($app->make(SessionManager::class), function () use ($app) {
                return $app->make(CacheFactory::class);
            });
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
