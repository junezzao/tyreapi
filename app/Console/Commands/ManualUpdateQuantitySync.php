<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\Eloquent\SyncRepository;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Product;
use Log;

class ManualUpdateQuantitySync extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ThirdParty:updateQuantity
                            {--channel_sku_ids= : Channel SKU IDs separated by comma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create updateQuantity sync';

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
		$this->info('Running... ThirdParty:updateQuantity');

        $channel_sku_ids = $this->option('channel_sku_ids');

        if(is_null($channel_sku_ids))
        {
            $this->error('Please input a list of Channel SKU IDs separated by comma.');
            return;
        }

        $channel_sku_ids = explode(',', $channel_sku_ids);
        foreach($channel_sku_ids as $channel_sku_id)
        {
            $this->info('Processing channel SKU #'. $channel_sku_id .' ...');

            $channel_sku = ChannelSKU::findOrFail($channel_sku_id);
            if(!Product::where('id', $channel_sku->product->id)->exists()) {
                $this->info('Product deleted for channel SKU #'. $channel_sku_id .'. Skipped.');
                continue;
            }

	    $syncRepo = new SyncRepository;
            $input['channel_sku_id'] = $channel_sku_id;
            $sync = $syncRepo->updateQuantity($input);  
        }
        $this->info('Finished!');
	}
}
