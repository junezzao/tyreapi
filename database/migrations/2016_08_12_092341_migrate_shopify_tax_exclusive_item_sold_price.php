<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateShopifyTaxExclusiveItemSoldPrice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('order_items')->where('tax_inclusive', 0)->where('tax', '>', 0)->chunk(1000, function ($items) {
            foreach ($items as $item) {
                $sold_price = $item->sold_price - $item->tax;
                DB::table('order_items')->where('id', $item->id)->update(array('sold_price' => $sold_price));
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('order_items')->where('tax_inclusive', 0)->where('tax', '>', 0)->chunk(1000, function ($items) {
            foreach ($items as $item) {
                $sold_price = $item->sold_price + $item->tax;
                DB::table('order_items')->where('id', $item->id)->update(array('sold_price' => $sold_price));
            }
        });
    }
}
