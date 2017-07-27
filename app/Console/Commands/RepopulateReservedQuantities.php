<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ReservedQuantityLog;
use App\Models\Admin\ChannelSKU;
use Log;
use \DB;

class RepopulateReservedQuantities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:repopulateReservedQuantities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repopulate reserved quantites and log';

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
        $this->info('Running at '.date('Y-m-d H:i:s'));

        $channel_skus = ChannelSKU::get();

        foreach($channel_skus as $channel_sku) {
            $channel_sku_id = $channel_sku->channel_sku_id;
            $this->info('Channel SKU #'.$channel_sku_id.' ...');

            $order_items = OrderItem::where('ref_type', 'ChannelSKU')->where('ref_id', $channel_sku_id)->get();
            $logs = array();
            foreach($order_items as $order_item) {
                $order = $order_item->order;
                $sort_date = '';
                if(is_null($order->reserved_date)) continue;

                // when order item is cancelled
                if($order_item->status == 'Cancelled') {
                    if(isset($order_item->returnLog)) {
                        $sort_date = $order_item->returnLog->created_at;
                        $logs[] = array(
                            'type' => 'decrement', 
                            'date' => $order_item->returnLog->created_at,
                            'order_id' => $order->id,
                            'order_status' => $order_item->returnLog->order_status,
                            'item_status' => 'Cancelled',
                            'item_id' => $order_item->id,
                            'sort_date' => $sort_date
                        );
                    } else {
                        $sort_date = $order->reserved_date;
                        $logs[] = array(
                            'type' => 'decrement', 
                            'date' => $order->created_at,
                            'order_id' => $order->id,
                            'order_status' => 'Unknown',
                            'item_status' => 'Cancelled',
                            'item_id' => $order_item->id,
                            'sort_date' => $sort_date
                        );
                    }
                } else {
                    if(!is_null($order->shipped_date)) {
                        $sort_date = $order->shipped_date;
                        $logs[] = array(
                            'type' => 'decrement', 
                            'date' => $order->shipped_date,
                            'order_id' => $order->id,
                            'order_status' => 'Shipped',
                            'item_status' => 'Verified',
                            'item_id' => $order_item->id,
                            'sort_date' => $sort_date
                        );
                    } else {
                        if(!is_null($order->cancelled_date)) {
                            $sort_date = $order->cancelled_date;
                            $logs[] = array(
                                'type' => 'decrement', 
                                'date' => $order->cancelled_date,
                                'order_id' => $order->id,
                                'order_status' => 'Cancelled',
                                'item_status' => 'Cancelled',
                                'item_id' => $order_item->id,
                                'sort_date' => $sort_date
                            );
                        }
                    }
                }

                $logs[] = array(
                    'type' => 'increment', 
                    'date' => $order->reserved_date,
                    'order_id' => $order->id,
                    'order_status' => 'New',
                    'item_status' => null,
                    'item_id' => $order_item->id,
                    'sort_date' => ($sort_date != '' && $order->reserved_date >= $sort_date) ? date('Y-m-d H:i:s', (strtotime(date($sort_date)) - 1)) : $order->reserved_date
                );
            }

            $date = array();
            foreach ($logs as $key => $log)
            {
                $date[$key] = $log['sort_date'];
            }
            array_multisort($date, SORT_ASC, $logs);

            foreach($logs as $log) {
                $this->info(($log['type'] == 'increment' ? '+' : '-').'   '.$log['date'].'    '.str_pad($log['order_id'], 5).'    '.str_pad($log['order_status'], 10).'   '.str_pad($log['item_status'], 10));
            }

            $new_quantity = 0;
            foreach($logs as $log) {
                $reserved_quantity = ReservedQuantity::where('channel_sku_id', $channel_sku_id)->first();
                if(is_null($reserved_quantity)) {
                    $old_quantity = 0;
                    $new_quantity = $old_quantity + ($log['type'] == 'increment' ? 1 : -1);
                    DB::table('reserved_quantities')->insert([
                        'channel_sku_id' => $channel_sku_id,
                        'quantity' => $new_quantity,
                        'created_at' => $log['date'],
                        'updated_at' => $log['date']
                    ]);
                } else {
                    $old_quantity = $reserved_quantity->quantity;
                    $new_quantity = $old_quantity + ($log['type'] == 'increment' ? 1 : -1);
                    DB::table('reserved_quantities')->where('channel_sku_id', $channel_sku_id)->update([
                        'quantity' => $new_quantity,
                        'updated_at' => $log['date']
                    ]);
                }
                
                DB::table('reserved_quantities_log')->insert([
                    'channel_sku_id' => $channel_sku_id,
                    'quantity_old' => $old_quantity,
                    'quantity_new' => $new_quantity,
                    'order_id' => $log['order_id'],
                    'order_status' => $log['order_status'],
                    'item_status' => $log['item_status'],
                    'item_id' => $log['item_id'],
                    'created_at' => $log['date']
                ]);
            }

            // if($new_quantity != 0) {
            //     $this->error('Alert for Channel SKU #'.$channel_sku_id);
            // }
        }

        $this->info('Finished at '.date('Y-m-d H:i:s'));
    }
}
