<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{

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
        $this->app->bind(
            'App\Repositories\Contracts\UserRepository',
            'App\Repositories\UserRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\MerchantRepository',
            'App\Repositories\Eloquent\MerchantRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\SupplierRepository',
            'App\Repositories\Eloquent\SupplierRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\IssuingCompanyRepository',
            'App\Repositories\Eloquent\IssuingCompanyRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\OrderRepository',
            'App\Repositories\Eloquent\OrderRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\ManifestRepository',
            'App\Repositories\Eloquent\ManifestRepository'
        );
        
        $this->app->bind(
            'App\Repositories\Contracts\OAuthRepositoryContract',
            'App\Repositories\OAuthRepository'
        );
        /*$this->app->bind(
            'App\Repositories\Contracts\ChannelRepository',
            'App\Repositories\ChannelRepository'
        );
        $this->app->bind(
            'App\Repositories\Contracts\SalesRepositoryContract',
            'App\Repositories\SalesRepository'
        );
        $this->app->bind(
            'App\Repositories\Contracts\StockTransferContract',
            'App\Repositories\StockTransferRepository'
        );
        $this->app->bind(
            'App\Repositories\Contracts\PartnerContract',
            'App\Repositories\PartnerRepository'
        );*/

        $this->app->bind(
            'App\Repositories\Contracts\DataSheetRepositoryContract',
            'App\Repositories\Eloquent\DataSheetRepository'
        );

        $this->app->bind(
            'App\Repositories\Contracts\DataRepositoryContract',
            'App\Repositories\Eloquent\DataRepository'
        );
    }
}
