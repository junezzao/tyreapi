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
	Route::group(['middleware' => 'oauth', 'before' => 'oauth'], function () {
		Route::group(['prefix' => 'reports'], function() {
			Route::post('/generate', 'ReportsController@show');
			Route::get('merchant/{id}/breakdown', 'ReportsController@merchantPerformance');
			Route::post('/third_party/process', 'ThirdPartyReportController@process');
			Route::get('/third_party/search', 'ThirdPartyReportController@search');
		    Route::get('/third_party/show/{id}', 'ThirdPartyReportController@show');
		    Route::post('/third_party/verify/{id}', 'ThirdPartyReportController@verify');
		    Route::post('/third_party/bulk_moveTo', 'ThirdPartyReportController@bulk_moveTo');
		    Route::post('/third_party/update/{id}', 'ThirdPartyReportController@update');
		    Route::delete('/third_party/destroy/{id}', 'ThirdPartyReportController@destroy');
			Route::get('/third_party/counters', 'ThirdPartyReportController@counters');
			Route::post('/third_party/complete_verified_order_items', 'ThirdPartyReportController@completeVerifiedOrderItems');
			Route::post('/third_party/export', 'ThirdPartyReportController@export');
			Route::post('/third_party/discardChecking', 'ThirdPartyReportController@discardChecking');
			Route::get('/third_party/num_verified_items', array('uses' => 'ThirdPartyReportController@countVerifiedOrderItems'));
			Route::post('/third_party/remark/{remarkId}/resolve', array('uses' => 'ThirdPartyReportController@resolveRemark'));
			Route::post('/third_party/{id}/addRemark', array('uses' => 'ThirdPartyReportController@addRemark'));
			Route::post('/third_party/generateReport', array('uses' => 'ThirdPartyReportController@generateReport'));
			Route::get('/', function() {
				dd('This is the Reports module index page.');
			});
		});
	});
});