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
		Route::group(['prefix' => 'admin'], function() {
			Route::get('/', function() {
				dd('This is the Channels module index page.');
			});

			Route::group(['prefix' => 'procurements'], function() {
				Route::post('{batch_id}/receive','Admin\PurchaseController@receive');
				Route::resource('{batch_id}/items', 'Admin\PurchaseItemController');
				Route::resource('{batch_id}/merchant/{merchant_id}/channel/{channel_id}/search', 'Admin\PurchaseController@findBatch');
				Route::get('{batch_id}/with-trashed', 'Admin\PurchaseController@showWithTrashed');
			});
			Route::resource('procurements', 'Admin\PurchaseController');
			

			Route::group(['prefix' => 'inventories'], function() {
				Route::get('getFiltersData','Admin\InventoryController@getFiltersData');
				Route::get('findBy','Admin\InventoryController@getProductDetails');
				
				// Bulk update product level
			    Route::get('bulk_update_products', 'Admin\InventoryController@bulkLoadProducts');
			    Route::post('bulk_update_products/save', 'Admin\InventoryController@bulkSaveProducts');

				// Bulk update categories
			    Route::get('categories', 'Admin\InventoryController@loadCategories');
			    Route::post('categories/save', 'Admin\InventoryController@saveCategories');

			    // Bulk update channel skus
			    Route::get('bulk_update', 'Admin\InventoryController@bulkLoad');
			    Route::post('bulk_update/save', 'Admin\InventoryController@bulkSave');

			    // Bulk reject channel skus
			    Route::post('bulk_reject', 'Admin\InventoryController@bulkReject');
			    Route::post('bulk_delete', 'Admin\InventoryController@bulkDelete');

                Route::post('sync_products/{type}', 'Admin\InventoryController@syncProducts');

                Route::get('channelSkus', 'Admin\InventoryController@getSkusByChannel');

                Route::post('get_chnl_sku_qty', 'Admin\InventoryController@getBulkSkus');

                Route::post('get_products', 'Admin\InventoryController@getProducts');

                Route::get('generate_channel_sku_list/{channelId}', 'Admin\InventoryController@getChannelSkusByChannel');
			});
			Route::resource('inventories', 'Admin\InventoryController');

			// Bulk get SKU details
            Route::post('sku/bulk/{col}','Admin\InventoryController@byColInBulk');

            Route::group(['prefix' => 'products'], function() {
            	Route::get('{brandId}/byBrand', 'Admin\InventoryController@getProductsByBrand');
            	Route::get('get_tp_item_details', 'Admin\InventoryController@getTpItemDetails');
            	Route::resource('{product_id}/tags', 'Admin\ProductTagController');
			    Route::post('{product_id}/medias/reorder', 'Admin\ProductMediaController@updateImgOrder');
			    Route::put('{product_id}/medias/reorder', 'Admin\ProductMediaController@updateImgOrder');
			    Route::post('{product_id}/medias/syncImages', 'Admin\InventoryController@syncImages');
			    Route::resource('{product_id}/medias', 'Admin\ProductMediaController');
            });
		    
			Route::group(['prefix' => 'stock_transfer'], function() {
				Route::get('{id}/manifest','Admin\StockTransferController@manifest');
				Route::post('process_sku/{merchant_id}','Admin\StockTransferController@processSKU');
				Route::post('transfer/{id}', 'Admin\StockTransferController@initiateTransfer');
			    Route::post('receive/{id}', 'Admin\StockTransferController@receiveStockTransfer');
			});
			Route::resource('stock_transfer', 'Admin\StockTransferController');

			// Reject Logs
			Route::resource('reject', 'Admin\RejectLogController');

			Route::get('stock_movements/{by}/{id}', 'Admin\InventoryController@getStockMovements');
		});
	});
});