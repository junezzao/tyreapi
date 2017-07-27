<?php
use App\Models\Admin\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateOrdersTableCancelledStatusToLastStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //select last cancelled log_id
        $last_order_log = DB::select(DB::raw("select max(`id`) as id from order_status_log last_cancelled_log where from_status <> to_status group by order_id"));
        $cancelled_orders_log = DB::table('order_status_log')
                                    ->join('sales', function ($join) {
                                        $join->on('sales.sale_id', '=', 'order_status_log.order_id')
                                        ->where('sales.sale_status', '=', 'Cancelled');
                                    })
                                    ->whereIn('id', array_pluck($last_order_log, 'id'))->get();

        //Get Orders which are cancelled and update
        foreach ($cancelled_orders_log as $order_log) {
            # code...
            $order = Order::find($order_log->order_id);
            //if canncelled status not found , use the last to_status as current status
            $last_status = (ucfirst($order_log->to_status) != 'Cancelled')?ucfirst($order_log->to_status):ucfirst($order_log->from_status);

            if ($order->status == 8) {
                if (isset(Order::$statusCode[$last_status])) {
                    //assign last status
                    $order->status = Order::$statusCode[$last_status];
                    //set cancelled as 1
                    $order->cancelled_status = 1;

                    $order->paid_status = (in_array(ucfirst($last_status), array('Pending', 'Failed'))) ? 0 : 1;
                    $order->save();
                }
            }
        }
        //when done check if there is any cancelled order left in the records and get the latest status , if latest status is still cancelled report issue else use lastest status
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $cancelled_sales = DB::table('sales')->where('sale_status', '=', 'Cancelled')->get();
        //get all orders find cancelled Order and change back to 8
        foreach ($cancelled_sales as $sale) {
            # code...
            $order = Order::find($sale->sale_id);
            $order->status = 8;
            $order->save();
        }
    }
}
