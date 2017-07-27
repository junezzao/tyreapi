<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\SalesRepository;

class SalesServiceProvider extends ServiceProvider
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
        $this->app->singleton('App\Repositories\Contracts\SalesRepositoryContract', function () {
            return new SalesRepository();
        });
    }
    
    public function provides()
    {
        return ['App\Repositories\Contracts\SalesRepositoryContract'];
    }
}
