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
		Route::group(['prefix' => 'contracts'], function() {
		   	Route::group(['prefix' => 'channels'], function() {
		   		Route::post('{id}/update-dates', 'ChannelContractsController@updateDate');
		   		Route::post('{id}/duplicate', 'ChannelContractsController@duplicate');
		   	});
	   		Route::resource('channels', 'ChannelContractsController');
		   	Route::post('/get-contracts', 'ContractsController@getContracts');
		   	Route::post('{id}/update-dates', 'ContractsController@updateDate');
			Route::get('/', 'ContractsController@index');
		   	Route::get('{id}', 'ContractsController@show');
		   	Route::post('/', 'ContractsController@store');
		   	Route::put('{id}', 'ContractsController@update');
		   	Route::post('{id}/duplicate', 'ContractsController@duplicate');
		   	Route::delete('{id}', 'ContractsController@destroy');
		   	Route::post('calculate_fee', 'ContractsController@calculateFee');
		    Route::post('exportFeeReport', 'ContractsController@exportFeeReport');
		});
	   	//Route::resource('contracts', 'ContractsController');
			
	});
});