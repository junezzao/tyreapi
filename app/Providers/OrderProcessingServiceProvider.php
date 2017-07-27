<?php 

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Facades\Controllers\OrderProcessingController;

class OrderProcessingServiceProvider extends ServiceProvider
{
    /**
    * Register the service provider
    *
    * @return void
    */
    public function register()
    {
        $this->app['orderProcessingFacade'] = $this->app->share(function ($app) {
            return new OrderProcessingController;
        });
    }
    
    public function boot()
    {
    }
}
