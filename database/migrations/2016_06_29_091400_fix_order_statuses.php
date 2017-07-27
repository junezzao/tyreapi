<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixOrderStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // reserved_status too
        // get all rows from sales table and compare the status, paid_status, cancelled_status and update the orders table accordingly
         DB::table('sales')->chunk(1000, function ($sales) {
            foreach ($sales as $sale) {
                $status = $this->getStatus($sale->sale_status);
                
                if ($status !== false) {
                    // paid_status
                    if ($status == 0 || $status == 1) {
                        $paid_status = 0;
                    } else {
                        $paid_status = 1;
                    }

                    // cancelled_status
                    if ($status != 8) {
                        $cancelled_status = 0;
                    } else {
                        $cancelled_status = 1;
                    }

                    if ($sale->sold_qty_cached == 1) {
                        $rq = 1;
                    } else {
                        $rq = 0;
                    }
                }
                
                DB::table('orders')->where('id', $sale->sale_id)->update(['status' => $status, 'cancelled_status' => $cancelled_status, 'paid_status' => $paid_status, 'reserved' => $rq]);
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
    }

    private function getStatus($status)
    {
        $status = ucfirst($status);
        $statuses = [ "Failed" => 0, "Pending" => 1, "New" => 2, "Picking" => 3, "Packing" => 4, "Ready To Ship" => 5, "Shipped" => 6, "Completed" => 7, "Cancelled" => 8];
        if (isset($statuses[$status])) {
            return $statuses[$status];
        }
        return false;
    }
}
