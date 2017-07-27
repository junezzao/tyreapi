<?php

namespace App\Console\Commands;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Admin\InventoryStockCache;
use App\Events\ChannelSkuQuantityChange;
use App\Models\Admin\QuantityLogApp;
use Schema;
use DB;

class dailyStockCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:dailyStockCache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture the Quantity and Reserved Quantity on Runtime';

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
        try {
            $now = Carbon::now()->toDateTimeString();
            \Log::info("Inventory Stock Cache Start ".Carbon::now());
            $this->comment('beginning copy channel_sku_quantity into inventory_stock_cache table');
            $this->comment('start time: '.$now);

            Schema::dropIfExists('channel_sku_snapshot');
            Schema::dropIfExists('reserved_quantities_snapshot');
            DB::statement('CREATE TABLE channel_sku_snapshot LIKE channel_sku');
            DB::statement('CREATE TABLE reserved_quantities_snapshot LIKE reserved_quantities');
            DB::Statement("INSERT INTO channel_sku_snapshot SELECT * from channel_sku");
            DB::Statement("INSERT INTO reserved_quantities_snapshot SELECT * from reserved_quantities");

            $channelSkus = DB::table('channel_sku_snapshot')
            ->leftJoin('reserved_quantities_snapshot','channel_sku_snapshot.channel_sku_id','=','reserved_quantities_snapshot.channel_sku_id')
            ->select(DB::raw("channel_sku_snapshot.channel_sku_id,channel_sku_snapshot.channel_sku_quantity,
                IF(reserved_quantities_snapshot.quantity IS NOT NULL, reserved_quantities_snapshot.quantity, 0) as reserved_quantity,channel_sku_snapshot.updated_at as last_stock_updated_at"))
            ->chunk(3000, function($chunk) use($now)
            {
                $chunk = json_decode(json_encode($chunk), true); // convert to array
                $from_ch_sku_id = isset($chunk[0]['channel_sku_id'])?$chunk[0]['channel_sku_id']:0;
                $this->info("Processing by chunk from channel_sku_id = ".$from_ch_sku_id);
                foreach ($chunk as $channel_sku) {
                    $cache = InventoryStockCache::firstOrNew($channel_sku);

                    //only save new changes
                    if (!$cache->exists) {
                        //if cache not exist
                        $cache->created_at = $now;
                        $cache->save();
                    }

                    //compare current quantity to last Quantity_log
                    $last_qty_log = QuantityLogApp::where('channel_sku_id','=',$channel_sku['channel_sku_id'])->where('triggered_at','<=',$now)->orderBy('triggered_at','desc')->first();
                    if(empty($last_qty_log) || $channel_sku['channel_sku_quantity'] !=$last_qty_log->quantity_new)
                    {
                        $oldQuantity = (empty($last_qty_log))?0:$last_qty_log->quantity_new;
                        event(new ChannelSkuQuantityChange($channel_sku['channel_sku_id'], $oldQuantity, 'Unknown',0, 'stockCache'));
                    }
                }
            });

            $this->comment('time completed: '.Carbon::now());
            \Log::info("Inventory Stock Cache Complete ".Carbon::now());

        } catch (Exception $e) {
            $ErrorDescription = 'Error: '. $e->getMessage() .' in '.$e->getFile().' at line '. $e->getLine();
            $this->error($ErrorDescription);
            Log::error($ErrorDescription);
        }
    }
}
