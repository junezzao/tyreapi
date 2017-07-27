<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ThirdPartySyncArchive;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\Product;
use DB;

class ArchiveSyncs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncs:archive
                                {--period=6 months : Archive syncs with sent_time older than this given period (counting from the time NOW)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive syncs that are older than a certain period of time';

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
        $period = $this->option('period');

        $now = strtotime('now');
        $this->info('DateTime now ' . date('Y-m-d H:i:s', $now));

        $start = date('Y-m-d H:i:s', strtotime('-' . $period, $now));
        $this->info('DateTime of ' . $period . ' before now (' . date('Y-m-d H:i:s', $now) . '): ' . $start);
        
        $this->info('Correcting any timestamp data error...');
        ThirdPartySync::where('created_at', '=', '0000-00-00 00:00:00')
                        ->chunk(1000, function ($syncs) {
                            foreach ($syncs as $sync) {
                                $sync->created_at = $sync->updated_at;
                                $sync->save();
                            }
                        });

        ThirdPartySync::where('updated_at', '=', '0000-00-00 00:00:00')
                        ->chunk(1000, function ($syncs) {
                            foreach ($syncs as $sync) {
                                $sync->updated_at = $sync->created_at;
                                $sync->save();
                            }
                        });

        $this->info('Archiving syncs with sent_time older than ' . $start);
        $syncIdsToDelete = array();
        ThirdPartySync::where('sent_time', '<', $start)
            ->where('sent_time', '<>', '0000-00-00 00:00:00')
            ->chunk(1000, function ($syncs) use (&$syncIdsToDelete) {
                foreach ($syncs as $sync) {
                    $this->comment('Archiving sync ' . $sync->id);
                    ThirdPartySyncArchive::firstOrCreate(json_decode(json_encode($sync), true));
                    $syncIdsToDelete[] = $sync->id;
                }
            });

        $this->info(count($syncIdsToDelete) . ' syncs archived.');

        if (count($syncIdsToDelete) > 0) {
            $this->info('Deleting archived syncs from third_party_sync table...');
            foreach ($syncIdsToDelete as $id) {
                ThirdPartySync::find($id)->delete();
            }
        }

        $this->info('Preparing data for updating sync status...');
        $syncData = array();
        ThirdPartySync::chunk(1000, function ($syncs) use (&$syncData) {
            foreach ($syncs as $sync) {
                if($sync->ref_table_id == 0) continue;
                $product_id = $this->getProductId($sync);
                if(Product::where('id', $product_id)->exists()) {
                    $syncData[] = array(
                        'channel_id'    => $sync->channel_id,
                        'product_id'    => $this->getProductId($sync)
                    );
                }
            }
        });

        $syncData = array_unique($syncData, SORT_REGULAR);
        $this->info('Updating sync status for sync indicators... (' . count($syncData) . ' products)');
        foreach ($syncData as $data) {
            $this->info('Updating sync status for product ' . $data['product_id'] . ' on channel ' . $data['channel_id'] . '...');
            ThirdPartySync::updateSyncStatus($data, true);
        }

        $this->info('Done at '.date('Y-m-d H:i:s'));
    }

    private function getProductId($sync) {
        switch($sync->ref_table){
            case 'ChannelSKU':
                $channelSku = ChannelSKU::findOrFail($sync->ref_table_id);
                $productId = $channelSku->product_id;
                break;
            case 'Product':
                $productId = $sync->ref_table_id;
                break;
            case 'ProductMedia':
                $productMedia = ProductMedia::withTrashed()->findOrFail($sync->ref_table_id);
                $productId = $productMedia->product_id;
                break;
            default:
                $productId = $sync->ref_table_id;
                break;
        }

        return $productId;
    }
}
