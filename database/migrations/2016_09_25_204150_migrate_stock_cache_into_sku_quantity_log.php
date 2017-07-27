<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\InventoryStockCache;
use App\Models\Admin\SKUQuantityLog;
class MigrateStockCacheIntoSkuQuantityLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $stockCacheDatetimes = InventoryStockCache::select('created_at')->groupBy('created_at')->get();
        DB::setFetchMode(PDO::FETCH_ASSOC);
        if(!empty($stockCacheDatetimes)){
            foreach ($stockCacheDatetimes as $stockCacheDate) {
                $created_at = $stockCacheDate->created_at;
                //get all stockCache on a specific date by sku_id
                //last stock updated at refers to the date in channel_sku.updated_at , intended save as created _at
                //created_at will be stored as updated_at to keep track on the date it is stored which is 3 secs late for inventory stock cache
                $query =
                'Select * from
                (SELECT
                    sku_id ,
                    sum(inventory_stock_cache.channel_sku_quantity) as quantity,
                    max(inventory_stock_cache.last_stock_updated_at) as created_at,
                    max(inventory_stock_cache.created_at) as updated_at
                    from
                        (SELECT channel_sku_id, MAX(created_at) as created_at
                        FROM inventory_stock_cache
                        WHERE inventory_stock_cache.created_at <= "'.$created_at.'"
                        GROUP BY channel_sku_id) inventory_stock_cache_date
                        LEFT JOIN inventory_stock_cache on inventory_stock_cache.created_at =inventory_stock_cache_date.created_at and inventory_stock_cache.channel_sku_id= inventory_stock_cache_date.channel_sku_id
                        LEFT JOIN channel_sku on channel_sku.channel_sku_id = inventory_stock_cache.channel_sku_id
                        GROUP BY channel_sku.sku_id
                ) new_stock_cache_by_sku  where new_stock_cache_by_sku.updated_at = "'.$created_at.'"';
                $skus = DB::select(DB::raw($query));
                SKUQuantityLog::insert($skus);
            }
        }else{
           Log::info('No stock cache found.');
        }
        DB::setFetchMode(PDO::FETCH_CLASS);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        SKUQuantityLog::whereNull('quantity_log_app_id')->delete();
    }
}
