<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\ChannelRepository;

class ChannelServiceProvider extends ServiceProvider
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
        $this->app->singleton('App\Repositories\Contracts\ChannelRepositoryContract', function () {
            return new ChannelRepository();
        });
    }
    
    public function provides()
    {
        return ['App\Repositories\Contracts\ChannelRepositoryContract'];
    }
}
