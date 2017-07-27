<?php

/*
|--------------------------------------------------------------------------
| Module Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for the module.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::group(['prefix' => '1.0'], function() {
	Route::group(['middleware' => 'oauth', 'before' => 'oauth'], function () {
		Route::group(['prefix' => 'channels'], function() {
			Route::get('/', function() {
				dd('This is the Channels module index page.');
			});

			Route::get('merchant/{merchant_id}', 'ChannelController@byMerchant');
			Route::get('merchant/{merchant_id}/channel_type/{chnl_type_id}', 'ChannelController@byMerchantAndChnlType');
			Route::get('merchant/{merchant_id}/with-trashed', 'ChannelController@byMerchantWithTrashed');
			Route::get('sync_history', 'ChannelController@getSyncHistory');
			Route::post('sync_history/retry/{sync_id}', 'ChannelController@retrySync');
			Route::post('sync_history/cancel/{sync_id}', 'ChannelController@cancelSync');
			Route::post('sync_history/bulk-update', 'ChannelController@bulkUpdateSyncStatus');
			Route::post('getShippingProvider', 'ChannelController@getShippingProvider');
			Route::get('{channel_id}/get_store_categories', 'ChannelController@getStoreCategories');
            Route::get('channel/{id}/with-trashed', 'ChannelController@showWithTrashed');
            Route::get('channel/{id}/get-storefrontapi', 'ChannelController@getStorefrontApi');
            Route::post('get-channels', 'ChannelController@getBulkChannels');
            Route::get('channel/{id}/get-issuing-company', 'ChannelController@getIssuingCompany');
            Route::get('channel/{id}/get-issuing-company', 'ChannelController@getIssuingCompany');
            Route::post('get-channels', 'ChannelController@getBulkChannels');

		    Route::resource('channel', 'ChannelController');

		    Route::group(['prefix' => 'admin'], function() {
		    	// Read categories from config file
				Route::get('categories/get', 'Admin\CategoriesController@getCategories');

				Route::group(['prefix' => 'channel_type'], function() {
					Route::get('{id}/get_outdated_categories_products', 'Admin\CategoriesController@getOutdatedCategoriesProducts');
			    	Route::put('{id}/update_categories', 'Admin\CategoriesController@updateThirdPartyProductCategories');
			    	Route::get('{id}/get_active_categories', 'Admin\CategoriesController@getActiveCategories');
			    	Route::put('{id}/remap_categories', 'Admin\CategoriesController@remapThirdPartyProductCategories');
			    	Route::post('{id}/update_status', 'Admin\ChannelTypeController@updateStatus');
			    	Route::get('get_mo_enabled', 'Admin\ChannelTypeController@getMOEnabledChannelTypes');
			    	Route::get('get_manifest_active_channels', 'Admin\ChannelTypeController@getManifestActiveChannels');
				});

            	Route::post('get-channel-types', 'Admin\ChannelTypeController@getBulkChannelTypes');
		        Route::resource('channel_type', 'Admin\ChannelTypeController');
		    });
		});
	});
});