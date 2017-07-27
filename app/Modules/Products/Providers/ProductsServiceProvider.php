<?php
namespace App\Modules\Products\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ProductsServiceProvider extends ServiceProvider
{
	/**
	 * Register the Products module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Products\Providers\RouteServiceProvider');

		$this->registerNamespaces();

		$this->app->bind(
            'App\Modules\Products\Repositories\Contracts\PurchaseRepositoryContract',
            'App\Modules\Products\Repositories\Eloquent\PurchaseRepository'
        );
		$this->app->bind(
            'App\Modules\Products\Repositories\Contracts\PurchaseItemRepositoryContract',
            'App\Modules\Products\Repositories\Eloquent\PurchaseItemRepository'
        );

	}

	/**
	 * Register the Products module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('products', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('products', base_path('resources/views/vendor/products'));
		View::addNamespace('products', realpath(__DIR__.'/../Resources/Views'));
	}
}
