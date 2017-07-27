<?php
namespace App\Modules\Fulfillment\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class FulfillmentServiceProvider extends ServiceProvider
{
	/**
	 * Register the Fulfillment module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Fulfillment\Providers\RouteServiceProvider');

		$this->registerNamespaces();

		$this->app->bind(
            'App\Modules\Fulfillment\Repositories\Contracts\ReturnRepository',
            'App\Modules\Fulfillment\Repositories\Eloquent\ReturnRepository'
        );
	}

	/**
	 * Register the Fulfillment module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('fulfillment', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('fulfillment', base_path('resources/views/vendor/fulfillment'));
		View::addNamespace('fulfillment', realpath(__DIR__.'/../Resources/Views'));
	}
}
