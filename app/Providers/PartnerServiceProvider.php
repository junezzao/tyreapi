<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\PartnerRepository;

class PartnerServiceProvider extends ServiceProvider
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
        $this->app->singleton('App\Repositories\Contracts\PartnerRepositoryContract', function () {
            return new PartnerRepository();
        });
    }
    
    public function provides()
    {
        return ['App\Repositories\Contracts\PartnerRepositoryContract'];
    }
}
