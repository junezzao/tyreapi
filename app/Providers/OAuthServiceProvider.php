<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\OAuthRepository;

class OAuthServiceProvider extends ServiceProvider
{
    protected $defer = true;
    
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('App\Repositories\Contracts\OAuthRepositoryContract', function () {
            return new OAuthRepository();
        });
    }
    
    public function provides()
    {
        return ['App\Repositories\Contracts\OAuthRepositoryContract'];
    }
}
