<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\ThirdPartySync;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use Log;

class PatchProductThirdPartyMediaSync extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ThirdParty:PatchProductMediaSync
                            {--channel_id= : Channel ID}
                            {--product_ids= : Product IDs separated by comma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Patch product third party media syncs.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->error_data['subject'] = 'Error '. $this->name;
        $this->error_data['File'] = __FILE__;
    }

	/**
     * Execute the console command.
     *
     * @return mixed
     */
	public function handle()
	{
		$this->info('Running... ThirdParty:PatchProductMediaSync');

        $channel_id = $this->option('channel_id');
        $product_ids = $this->option('product_ids');

        if(is_null($channel_id))
        {
            $this->error('Please input Channel ID.');
            return;
        }

        if(is_null($product_ids))
        {
            $this->error('Please input a list of product IDs separated by comma.');
            return;
        }

        $channel = Channel::with('channel_type')->where('id', $channel_id)->firstOrFail();
        $product_ids = explode(',', $product_ids);
        foreach($product_ids as $product_id)
        {
            $product = Product::where('id', $product_id)->firstOrFail();
            $this->info('Processing product #'. $product_id .' ...');

            $parent = null;
            $channel_sku = ChannelSKU::where('product_id', $product_id)
                            ->where('channel_id', $channel_id)
                            ->with('sku')
			    ->orderBy('channel_sku.sku_id','asc')
                            ->firstOrFail();

            $productTp = ProductThirdParty::firstOrNew(array('product_id'=>$product_id, 'channel_id'=>$channel_id));
            $productTp->ref_id = $product_id;
            $productTp->third_party_name = $channel->channel_type->name;
            $productTp->extra = json_encode(array('parentSku'=>$channel_sku->sku->hubwire_sku));
            $productTp->save();

            $sync = new ThirdPartySync;
            $sync->channel_id       = $channel_id;
            $sync->channel_type_id  = $channel->channel_type->id;
            $sync->ref_table        = 'Product';
            $sync->ref_table_id     = $product_id;
            $sync->action           = 'updateMedia';
            $sync->sync_type        = 'auto';
            $sync->trigger_event    = 'Create Product'.sprintf(' #%06d',$product_id);
            $sync->status           = 'NEW';
            $sync->remarks          = '';
            $sync->sent_time        = date('Y-m-d H:i:s');
            $sync->merchant_id      = $product->merchant_id;
            $sync->extra_info       = '';
            $sync->save();    
 
            $channel_sku->ref_id = $channel_sku->channel_sku_id;
            $channel_sku->save();   
        }
        $this->info('Finished!');
	}
}
