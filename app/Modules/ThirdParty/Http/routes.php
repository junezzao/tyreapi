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
Route::group(['prefix' => '1.0'], function(){
	Route::group(['prefix' => 'thirdparty'], function() {
		Route::get('ebay', 'EbayController@index');
		Route::get('ebay/getCategories/{site}', 'EbayController@getCategories');
		Route::get('getImageDimensions', 'ThirdPartyController@getImageDimensions');
		Route::post('import_store_categories/{channel_id}', 'ThirdPartyController@importStoreCategories');
	});

	Route::group(['prefix' => 'webhook'], function() {
		Route::post('{channel}/{module}/{event}', 'OrderProcessingService@main_receiver');
		Route::post('register/{channel_id}', 'ThirdPartyController@registerWebhooks');
	});
});