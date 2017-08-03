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

	Route::group(['prefix' => 'hw'], function () {
	    Route::get('login', array('uses' => 'Auth\AuthController@getLogin', 'as' => 'hw.getlogin'));
	    Route::post('login', array('uses' => 'Auth\AuthController@postLogin', 'as' => 'hw.login'));

	    Route::get('logout', array('uses' => 'Auth\AuthController@getLogout', 'as' => 'hw.logout' ));

	    Route::post('/password/forgot', array('uses' => 'Auth\AuthController@forgot', 'as' => 'hw.password.forgot'));
	});

	Route::group(['middleware' => 'auth:web'], function () {
	    Route::group(['prefix' => 'hw'], function () {
		    Route::get('dashboard', array('uses' => 'DashboardController@index', 'as' => 'hw.dashboard' ));
		    Route::resource('changelog', 'ChangelogController');
	    	Route::get('verify', array('uses' => 'Auth\AuthController@showVerify', 'as' => 'hw.users.show_verify'));
	    	Route::post('users/verify/', array('uses' => 'Auth\AuthController@verify', 'as' => 'hw.users.verify'));
		});
	});

	Route::get('changelog_data', array('uses' => 'ChangelogController@getChangelog', 'as' => 'changelog.get'));
	Route::get('changelog/{type}', 'ChangelogController@viewChangelogPublic');

	// API Explorer
	Route::group(['prefix' => 'api-explorer'], function() {
		Route::get('/', 'TestController@index');
		Route::post('get-token', array('uses' => 'TestController@getAccessToken', 'as' => 'test_api.get_token'));
		Route::post('perform-action', array('uses' => 'TestController@performAction', 'as' => 'test_api.perform_action'));
		Route::post('get-webhook-url', array('uses' => 'TestController@getWebhookUrls', 'as' => 'test_api.get_webhook_url'));
		Route::post('perform-webhook', array('uses' => 'TestController@performWebhookAction', 'as' => 'test_api.perform_webhook'));
	});

	//Events
	Route::post('/events/listener','EventController@listen');
	Route::group(['middleware'=>['store'] ], function(){
			Route::resource('sku','SKUController');
			Route::resource('webhooks','WebhooksController');
			Route::resource('products','ProductsController');
			Route::resource('sales','OrdersController');
			Route::resource('sales/{sale_id}/items','OrdersItemsController');
	});
	Route::post('oauth/access_token', function() {
	    return Response::json(Authorizer::issueAccessToken());
	});

	Route::post('users/forgot', 'Auth\OAuthController@passwordReset');
	Route::group(['prefix' => 'mobile','middleware' => 'oauth', 'before' => 'oauth'], function(){
		Route::post('users/forgot', 'Auth\MobileAuthController@passwordReset');
		Route::get('merchant', 'Mobile\MainController@merchant');
		Route::get('getLoggedInUser', 'Admin\UsersController@getLoggedInUser');
		Route::get('statistics', 'StatisticsController@merchantStats');
		Route::get('dashboard_counters', array('uses'=> 'StatisticsController@counters'));
	    	
			
		Route::group(['prefix' => 'orders'], function() {

			Route::get('update/{id}', function(){
				return 'am in the get path';
			});
			Route::get('search', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@searchDB');
			Route::get('levels', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@countLevels');
			Route::get('count', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@countOrders');
			Route::get('getorder/{channel_id}/{order_code}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getThirdPartyOrder');
			Route::post('create', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@createManualOrder');
			Route::post('update/{id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@update');
			Route::get('getitems/{id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getItems');
			Route::get('getnotes/{id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getNotes');
			Route::get('gethistory/{id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getHistory');
			Route::get('get_return_log/{order_id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getReturnsAndCancelledItems');
			Route::get('get_promotion_codes/{orderId}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getPromotionCodes');
			Route::get('get_order_sheet_info/{orderId}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getOrderSheetInfo');
			Route::get('get_return_slip_info/{orderId}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@getReturnSlipInfo');
			Route::get('{id}', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@show');
			Route::post('/{order_id}/item/{item_id}/cancel', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@cancelItem');
        	Route::post('/{order_id}/item/{item_id}/return', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@returnItem');
        	Route::post('{id}/readyToShip','\App\Modules\Fulfillment\Http\Controllers\OrdersController@readytoship');
			Route::post('{order_id}/item/pack', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@packItem');
			Route::post('{order_id}/item/updateStatus', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@updateItemStatus');
			Route::post('/{id}/createnote', '\App\Modules\Fulfillment\Http\Controllers\OrdersController@createNote');
		});
	});


    Route::group(['middleware' => 'oauth', 'before' => 'oauth'], function () {
        Route::get('oauth/logout', 'Auth\OAuthController@appLogout');
        Route::post('users/verify', array('uses' => 'UsersController@verify'));
        Route::post('media/bulkUpload', 'MediaController@storeMultiple');
        Route::resource('media', 'MediaController');

        Route::post('print/{document_type}/{order_id}', 'OrdersController@printDocument');

        // custom fields routes
        Route::resource('custom_fields', 'CustomFieldsController');
        Route::get('custom_fields/channel/{channel_id}', 'CustomFieldsController@getCfByChannel');
        Route::post('custom_fields/channel/{channel_id}/updateCF', 'CustomFieldsController@updateCF');
        Route::post('custom_fields/channel/{channel_id}/deleteCF', 'CustomFieldsController@deleteCF');
        Route::get('custom_fields/channel_sku/{id}', 'CustomFieldsController@getCFData');

		Route::group(['prefix' => 'modules'], function(){
			Route::get('/', 'Admin\ModulesController@getModules');
			Route::get('/getModuleDetails', 'Admin\ModulesController@getModuleDetails');
			Route::get('/enable', 'Admin\ModulesController@enableModule');
			Route::get('/disable', 'Admin\ModulesController@disableModule');
		});

		/*
		 // Not valid anymore use Mobile routes instead
		Route::group(['prefix' => 'statistics'], function(){
			// Merchant-specific statistics
			Route::group(['prefix'=>'merchant'], function() {
				Route::get('{id}', 'StatisticsController@merchantStats');
			});
		});
		*/

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
			// dashboard widgets routes
        	Route::get('dashboard', 'StatisticsController@index');
        	Route::get('dashboard_counters', array('uses'=> 'StatisticsController@counters'));
	    	
			Route::get('user/{id}', 'Admin\UsersController@getUser');
			Route::get('getLoggedInUser', 'Admin\UsersController@getLoggedInUser');
			Route::get('getLoggedInUserPermission', 'Admin\UsersController@getLoggedInUserPermission');
            //Route::get('user/{id}', 'Admin\UsersController@getUser');
            Route::post('users/create', 'Admin\UsersController@store');
            Route::post('users/subscribe/{userId}', 'Admin\UsersController@subscribe');
            Route::get('suppliers/{merchantId}/byMerchant', 'Admin\SuppliersController@byMerchant');
            Route::get('suppliers/{id}/with-trashed', 'Admin\SuppliersController@showWithTrashed');
            Route::get('merchants/with-trashed', 'Admin\MerchantsController@indexWithTrashed');
            Route::post('merchants/new-signups', 'Admin\MerchantsController@getNewMerchantsByMonth');
            Route::post('merchants/last-live', 'Admin\MerchantsController@getActiveMerchants');
            Route::get('merchants/{id}/with-trashed', 'Admin\MerchantsController@showWithTrashed');
            Route::get('merchants/{channelId}/byChannel', 'Admin\MerchantsController@getMerchantsByChannel');
            Route::get('brands/{merchantId}/byMerchant', 'Admin\BrandsController@getBrandsByMerchant');
            Route::resource('merchants', 'Admin\MerchantsController');
		    Route::resource('suppliers', 'Admin\SuppliersController');
            Route::resource('issuing_companies', 'Admin\IssuingCompanyController');
            Route::resource('users', 'Admin\UsersController');
		    Route::resource('brands', 'Admin\BrandsController');
		    Route::resource('categories', 'Admin\CategoryController');

		    Route::post('generate-report/search', 'Admin\GenerateReportController@getDataTable');

		    //Testing Routes
		    Route::group(['prefix' => 'testing'], function() {
		    	Route::post('run_syncs', function () {
				    Artisan::call('ThirdParty:sync');
				});

		    	Route::get('get_hapi_logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

		    	Route::get('generate_stats', function() {
		    		Artisan::call('statistics:generateDashboardStats');
		    	});
			});
			//END
        });

		Route::resource('member', 'MembersController');
        /*Route::group(['prefix' => 'member'], function() {
	        Route::get('{id}', 'MembersController@show');
	    });*/
    });
});
