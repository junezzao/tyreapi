<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Channel;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\DeliveryOrderItem;
use App\Models\Admin\ChannelSKU;
use Carbon\Carbon;
use Activity;
use Log;
use DB;
use App\Events\ChannelSkuQuantityChange;
class StockTransferCorrectionMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:clearCorrectionChannels {channel_id? : Enter the channel_id to migrate for a specific channel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to migrate correction channels data';

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
        $this->info('Begin migrating data from correction channels ...');
        $channel_id = $this->argument('channel_id');

        $channels = ( isset($channel_id) ? Channel::where('id', $channel_id)->get() : Channel::all() );
        $correctionChannels = array();

        foreach ($channels as $channel) {
            if(strpos($channel->name, 'Correction')){
                $correctionChannels[] = ['id' => $channel->id, 'name' => $channel->name];
            }
        }

        if(!empty($correctionChannels)){
            $csku = array();
            foreach ($correctionChannels as $correctionChannel) {
                // get all delivery order (stock transfer) into the channel
                $deliveries = DeliveryOrder::where('target_channel_id', $correctionChannel['id'])->orWhere('originating_channel_id', $correctionChannel['id'])->get();
                foreach ($deliveries as $delivery) {
                    $this->info('Working on DO: '.$delivery->id);
                    Log::info('Working on DO: '.$delivery->id);
                    $items = DeliveryOrderItem::where('do_id', $delivery->id)->get();
                    foreach ($items as $item) {
                        $this->info('Moving item: '.$item->sku_id);
                        Log::info('Moving item: '.$item->sku_id);

                        $source = ($delivery->target_channel_id == $correctionChannel['id'])?'target':'originating';

                        // store to stock_correction table
                        $stock_correction_id = DB::table('stock_correction')->insertGetId(
                            [
                             'do_id' => $delivery->id,
                             'sku_id' => $item->sku_id,
                             'quantity' => (strcmp($source,'target')==0)?-$item->quantity:$item->quantity, //determine if stock correction by adding or removing stocks
                             'remarks' => $delivery->remarks,
                             'user_id' => $delivery->user_id,
                             'corrected_at' => $delivery->receive_at,
                             'created_at' => Carbon::now(),
                             'updated_at' => Carbon::now(),
                            ]
                        );
                        if(strcmp($source,'target')==0)
                        {
                            // remove quantity from channel sku. No need to create reject log entry
                            $channelSku = ChannelSKU::where('channel_id', '=',$correctionChannel['id'])->where('sku_id','=',$item->sku_id)->first();
                            if($channelSku){
                                // $oldQuantity = $channelSku->channel_sku_quantity;
                                // $channelSku->decrement('channel_sku_quantity', $item->quantity);
                                // $channelSku->touch();
                                // event(new ChannelSkuQuantityChange($channelSku->channel_sku_id, $oldQuantity, 'StockCorrection', $stock_correction_id));
                                event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $item->quantity, 'StockCorrection', $stock_correction_id, 'decrement'));
                                $this->info('Moved item: '.$item->sku_id);
                                $csku[] = $channelSku->channel_sku_id;
                            }
                        }
                        // delete the stock transefer/delivery order items
                        $item->delete();
                    }
                    // delete the stock transfer/delivery order
                    $delivery->delete();
                    $this->info('Deleted DO: '.$delivery->id);
                    Log::info('Deleted DO: '.$delivery->id);
                    Activity::log('Migrated DO ('.$delivery->id.') from '.$correctionChannel['name'].' to stock_correction table.', null);
                }
            }
            // set affected channel sku to inactive
            foreach ($csku as $c) {
                $channelSku = ChannelSKU::find($c);
                $channelSku->channel_sku_active = false;
                $channelSku->save();
                $this->info('Set ChannelSKU to inactive: '.$channelSku->channel_sku_id);
                Log::info('Set ChannelSKU to inactive: '.$channelSku->channel_sku_id);
            }
        }else{
            $this->info('No correction channels found.');
            Log::info('No correction channels found.');
        }

    }
}
