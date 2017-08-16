<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
Route::get('/', function () {
    return app()->environment();
});

Route::get('/phpinfo', function(){
	return phpinfo();
});

Route::group(['prefix' => '1.0'], function(){
	Route::get('/', function() {
		return view('welcome');
	});

	Route::post('auth/register', 'Auth\AuthController@store');

	Route::post('oauth/access_token', function() {
	    return Response::json(Authorizer::issueAccessToken());
	});

	Route::post('users/forgot', 'Auth\OAuthController@passwordReset');

    Route::group(['middleware' => 'oauth', 'before' => 'oauth'], function () {
        Route::get('oauth/logout', 'Auth\OAuthController@appLogout');
        Route::post('users/verify', array('uses' => 'UsersController@verify'));

		Route::group(['prefix' => 'data'], function () {
			Route::get('/sheet/{user_id}', 'DataController@getSheet');
			Route::get('/{user_id}', 'DataController@getData');
			Route::get('/{user_id}/view/truck_position', 'DataController@viewTruckPosition');
			Route::get('/{user_id}/view/truck_service', 'DataController@viewTruckService');
			Route::get('/{user_id}/view/tyre_brand', 'DataController@viewTyreBrand');
		});

		Route::resource('data', 'DataController');

		Route::group(['prefix' => 'reports'], function () {
			Route::get('/{user_id}/serial_no_analysis', 'ReportController@serialNoAnalysis');
			Route::get('/{user_id}/odometer_analysis', 'ReportController@odometerAnalysis');
			Route::get('/{user_id}/tyre_removal_mileage', 'ReportController@tyreRemovalMileage');
			Route::get('/{user_id}/tyre_removal_record', 'ReportController@tyreRemovalRecord');
			Route::get('/{user_id}/truck_tyre_cost', 'ReportController@truckTyreCost');
			Route::get('/{user_id}/truck_service_record', 'ReportController@truckServiceRecord');
		});

		Route::resource('reports', 'ReportController');

		Route::group(['prefix' => 'admin'], function() {
			Route::get('user/{id}', 'Admin\UsersController@getUser');
			Route::get('getLoggedInUser', 'Admin\UsersController@getLoggedInUser');
			Route::get('getLoggedInUserPermission', 'Admin\UsersController@getLoggedInUserPermission');
            Route::post('users/subscribe/{userId}', 'Admin\UsersController@subscribe');

            Route::resource('users', 'Admin\UsersController');
        });
    });
});
