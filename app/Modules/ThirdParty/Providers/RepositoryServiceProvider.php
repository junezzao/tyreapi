<?php 

namespace App\Modules\ThirdParty\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'App\Modules\ThirdParty\Repositories\Contracts\ThirdPartyRepository',
            'App\Modules\ThirdParty\Repositories\Eloquent\EbayRepository'
        );
    }
}
