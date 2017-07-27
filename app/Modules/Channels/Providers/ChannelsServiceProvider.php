<?php
namespace App\Modules\Channels\Providers;

use App;
use Config;
use Lang;
use View;
use Illuminate\Support\ServiceProvider;

class ChannelsServiceProvider extends ServiceProvider
{
	/**
	 * Register the Channels module service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		// This service provider is a convenient place to register your modules
		// services in the IoC container. If you wish, you may make additional
		// methods or service providers to keep the code more focused and granular.
		App::register('App\Modules\Channels\Providers\RouteServiceProvider');

		$this->registerNamespaces();

		$this->app->bind(
            'App\Modules\Channels\Repositories\Contracts\ChannelTypeRepositoryContract',
            'App\Modules\Channels\Repositories\Eloquent\ChannelTypeRepository'
        );

        $this->app->bind(
            'App\Modules\Channels\Repositories\Contracts\ChannelRepositoryContract',
            'App\Modules\Channels\Repositories\Eloquent\ChannelRepository'
        );
	}

	/**
	 * Register the Channels module resource namespaces.
	 *
	 * @return void
	 */
	protected function registerNamespaces()
	{
		Lang::addNamespace('channels', realpath(__DIR__.'/../Resources/Lang'));

		View::addNamespace('channels', base_path('resources/views/vendor/channels'));
		View::addNamespace('channels', realpath(__DIR__.'/../Resources/Views'));
	}
}
