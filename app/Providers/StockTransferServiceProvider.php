<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\StockTransferRepository;

class StockTransferServiceProvider extends ServiceProvider
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
        $this->app->singleton('App\Repositories\Contracts\StockTransferRepositoryContract', function () {
            return new StockTransferRepository();
        });
    }
    
    public function provides()
    {
        return ['App\Repositories\Contracts\StockTransferRepositoryContract'];
    }
}
