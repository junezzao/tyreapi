<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Order;
use App\Models\Admin\ReservedQuantity;
use DB;

class PopulateReservedQuantities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:populateReservedQuantities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate existing orders into reserved_quantities table';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // create backup
        DB::statement('DROP TABLE IF EXISTS `reserved_quantities_bk`');
        DB::statement('RENAME TABLE `reserved_quantities` TO `reserved_quantities_bk`');
        DB::statement('CREATE TABLE `reserved_quantities` LIKE `reserved_quantities_bk`');
        DB::statement('DROP TABLE IF EXISTS `reserved_quantities_log_bk`');
        DB::statement('RENAME TABLE `reserved_quantities_log` TO `reserved_quantities_log_bk`');
        DB::statement('CREATE TABLE `reserved_quantities_log` LIKE `reserved_quantities_log_bk`');

        // updated all current orders reserved to 0
        DB::table('orders')->where('reserved', '1')->chunk(1000, function($ordersTbl){
            foreach($ordersTbl as $o){
                DB::table('orders')->where('id', $o->id)->update(['reserved' => 0]);
            }
        });

        $orders = DB::select(DB::raw("
            SELECT 
                orders.id,
                orders.created_at,
                orders.updated_at, 
                order_items.id as 'item_id',
                order_items.ref_id,
                order_items.original_quantity
            FROM orders
            JOIN order_items ON orders.id = order_items.order_id
            JOIN channels ON orders.channel_id = channels.id
            WHERE orders.status IN (".Order::$newStatus.", ".Order::$pickingStatus.", ".Order::$packingStatus.", ".Order::$readyToShipStatus.")
            AND orders.cancelled_status = 0
            AND orders.shipping_notification_date is null
            AND order_items.ref_type = 'ChannelSKU'
            AND order_items.fulfilled_channel != 0
        "));

        foreach ($orders as $order) {
            $this->info('Order ID : ' . $order->id . ' --> Item ID : ' . $order->item_id);
        }

        $this->info('Total number of orders affected : ' . count($orders));

        if ($this->confirm('Do you wish to continue? [y|n]'))
        $counter = 0;
        {
            foreach ($orders as $order) {
                $this->info('Processing... Order ID : ' . $order->id . ' --> Item ID : ' . $order->item_id);

                $order_order = Order::where('id', '=', $order->id)->update(array('reserved' => 1));

                $reservedQuantity = ReservedQuantity::where('channel_sku_id', '=', $order->ref_id)->first();
                if(is_null($reservedQuantity)) {
                    $reservedQuantity = new ReservedQuantity;

                    $reservedQuantity->channel_sku_id = $order->ref_id;
                    $reservedQuantity->quantity = $order->original_quantity;
                    $reservedQuantity->created_at = $reservedQuantity->updated_at = $order->created_at;
                }
                else {
                    $reservedQuantity->quantity += $order->original_quantity;
                    $reservedQuantity->updated_at = $order->updated_at;
                }

                $reservedQuantity->save();
                $counter++;
            }

            $this->info(count($counter) . ' orders processed. Completed!');
        }
    }
}
