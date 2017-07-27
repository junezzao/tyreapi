<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Order;

class AddColumnToReturnLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('return_log', function ($table) {
            $table->string("order_status", 50)->after('status');            
        });

        $statusCode = array_flip(Order::$statusCode);

        DB::table('return_log')->chunk(1000, function($returns) use ($statusCode) {
            foreach ($returns as $return) {
                if ($return->order_item_id!=0 && $return->order_id!=0) {
                    $order = DB::table('orders')->where('id', $return->order_id)->select('status')->first();       
                    
                    if (isset($order->status))        
                        DB::table('return_log')->where('id', $return->id)->update(['order_status' => isset($statusCode[$order->status])?$statusCode[$order->status]:$order->status]);   
                }
                
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
        //
        Schema::table('return_log', function ($table) {
            $table->dropColumn('order_status');
        });
    }
}
