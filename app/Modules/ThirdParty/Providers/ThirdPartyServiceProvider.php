<?php
namespace App\Modules\ThirdParty\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ThirdPartyServiceProvider extends ServiceProvider
{
	/**
	 * Register the ThirdParty module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\ThirdParty\Providers\RouteServiceProvider');
		App::register('App\Modules\ThirdParty\Providers\RepositoryServiceProvider');

		$this->registerNamespaces();
	}

	/**
	 * Register the ThirdParty module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('thirdparty', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('thirdparty', base_path('resources/views/vendor/thirdparty'));
		View::addNamespace('thirdparty', realpath(__DIR__.'/../Resources/Views'));
	}
}
