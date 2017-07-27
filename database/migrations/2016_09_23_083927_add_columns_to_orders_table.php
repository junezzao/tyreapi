<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\DeliveryOrder;

class AddColumnsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('orders', function ($table) {
            $table->dateTime('shipped_date')->nullable()->default(null)->after('tp_extra');
            $table->dateTime('cancelled_date')->nullable()->default(null)->after('tp_extra');
            $table->dateTime('reserved_date')->nullable()->default(null)->after('tp_extra');
        });


        DB::table('orders')->chunk(1000, function($orders){
            foreach ($orders as $order) {
                // shipped date - when status = ready to ship -> get created_at from order_status_log table
                $status = DB::table('order_status_log')
                            ->where('order_id', $order->id)
                            ->where(function ($query) {
                                $query->where('to_status', '=', 'Shipped')
                                      ->orWhere('to_status', '=', 31);
                            })
                            ->first();
                if (!empty($status)) {
                    DB::table('orders')->where('id', $order->id)->update(['shipped_date' => $status->created_at ]);
                }

                // cancelled date - when status = cancelled -> get updated_at from orders table
                if ($order->cancelled_status==1)
                    DB::table('orders')->where('id', $order->id)->update(['cancelled_date' => $order->updated_at ]);

                // reserved date - when fulfilled channel !empty -> get paid date
                $items = DB::table('order_items')->where('order_id', $order->id)->get();
                foreach ($items as $item) {
                    if (isset($item->fulfilled_channel) && !empty($item->fulfilled_channel)) {
                        DB::table('orders')->where('id', $order->id)->update(['reserved_date' => $order->paid_date ]);
                        break;
                    }
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
        Schema::table('orders', function ($table) {
            $table->dropColumn(['shipped_date', 'cancelled_date', 'reserved_date']);
        });
    }
}
