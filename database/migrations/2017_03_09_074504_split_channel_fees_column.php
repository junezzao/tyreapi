<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SplitChannelFeesColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report', function ($table) {
            $table->decimal('channel_shipping_fees', 10, 2)->after('channel_fees')->default(0);
            $table->decimal('channel_payment_gateway_fees', 10, 2)->after('channel_shipping_fees')->default(0);
        }); 

        Schema::table('order_items', function ($table) {
            if (Schema::hasColumn('order_items', 'merchant_shipping_fee')) {
                $table->decimal('channel_payment_gateway_fees', 10, 2)->after('merchant_shipping_fee')->default(0);    
            }else{
                $table->decimal('channel_payment_gateway_fees', 10, 2)->after('merchant_id')->default(0);
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
        Schema::table('third_party_report', function ($table) {
            $table->dropColumn('channel_shipping_fees');
            $table->dropColumn('channel_payment_gateway_fees');
        });
        Schema::table('order_items', function ($table) {
            $table->dropColumn('channel_payment_gateway_fees');
        });
    }
}
