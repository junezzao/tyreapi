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

		Route::group(['prefix' => 'orders'], function() {

			Route::get('update/{id}', function(){
				return 'am in the get path';
			});
			Route::get('search', 'OrdersController@search');
			Route::get('levels', 'OrdersController@countLevels');
			Route::get('count', 'OrdersController@countOrders');
			Route::get('getorder/{channel_id}/{order_code}', 'OrdersController@getThirdPartyOrder');
			Route::post('create', 'OrdersController@createManualOrder');
			Route::post('update/{id}', 'OrdersController@update');

			Route::get('getitems/{id}', 'OrdersController@getItems');
			Route::get('getnotes/{id}', 'OrdersController@getNotes');
			Route::get('gethistory/{id}', 'OrdersController@getHistory');
			Route::get('get_return_log/{order_id}', 'OrdersController@getReturnsAndCancelledItems');
			Route::get('get_promotion_codes/{orderId}', 'OrdersController@getPromotionCodes');
			Route::get('get_order_sheet_info/{orderId}', 'OrdersController@getOrderSheetInfo');
			Route::get('get_return_slip_info/{orderId}', 'OrdersController@getReturnSlipInfo');
			Route::get('{id}', 'OrdersController@show');
			Route::post('/{order_id}/item/{item_id}/cancel', 'OrdersController@cancelItem');
        	Route::post('/{order_id}/item/{item_id}/return', 'OrdersController@returnItem');
        	Route::post('{id}/readyToShip','OrdersController@readytoship');
			Route::post('{order_id}/item/pack', 'OrdersController@packItem');
			Route::post('{order_id}/item/updateStatus', 'OrdersController@updateItemStatus');


			Route::post('/{id}/createnote', 'OrdersController@createNote');
		});

        Route::group(['prefix' => 'fulfillment'], function() {
			Route::get('/', function() {
				dd('This is the Fulfillment module index page.');
			});

			Route::post('order/cancel/{order_id}', 'ReturnController@cancelOrder');
			Route::post('return/search', 'ReturnController@search');
            Route::resource('return', 'ReturnController');

            // Picking Manifest routes
            Route::get('manifest/search', 'ManifestController@search');
            Route::get('manifest/count', 'ManifestController@count');
            Route::post('manifest/{id}/completed', 'ManifestController@completed');
            Route::post('manifest/{id}/cancel', 'ManifestController@cancel');
            Route::get('manifest/{id}/items', 'ManifestController@pickingItems');
            Route::get('manifest/{id}/orders', 'ManifestController@getUniqueOrders');
            Route::post('manifest/{id}/outofstock', 'ManifestController@outOfStock');   
            Route::post('manifest/{id}/items/pick', 'ManifestController@pickItem');
            Route::post('manifest/pickup', 'ManifestController@pickUpManifest');
            Route::get('manifest/{id}/export_pos_laju', 'ManifestController@exportPosLaju');
            Route::get('manifest/{id}/print_documents', 'ManifestController@printDocuments');
            Route::post('manifest/{id}/assign_user', 'ManifestController@assignUser');

            Route::resource('manifest', 'ManifestController');

            Route::get('failed_orders/{id}/discard', 'FailedOrdersController@discard');
            Route::get('failed_orders/{id}/pending', 'FailedOrdersController@pending');
            Route::resource('failed_orders', 'FailedOrdersController');

        });
    });
});