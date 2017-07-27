<?php
namespace App\Modules\Contracts\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ContractsServiceProvider extends ServiceProvider
{
	/**
	 * Register the Contracts module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Contracts\Providers\RouteServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the Contracts module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('contracts', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('contracts', base_path('resources/views/vendor/contracts'));
		View::addNamespace('contracts', realpath(__DIR__.'/../Resources/Views'));
	}
}
