<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\SKU;
use Log;
use DB;

class DuplicateProductSkuInfo extends Command
{
	/**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:duplicateProductSkuInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Duplicate product, SKU and image data, based on table SKU_MAPPING';

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
		$this->info('Running... Command:DuplicateProductSkuInfo');

        $data = DB::table('sku_mapping')->whereNotNull('new_sku_id')->whereNull('completed_at')->get();

        foreach ($data as $d) {
            $sku = SKU::where('sku_id', $d->sku_id)->firstOrFail();
            $newSku = SKU::where('sku_id', $d->new_sku_id)->firstOrFail();

            $prefix = '[Hubwire SKU updated] ';
            if (substr($sku->product->name, 0, strlen($prefix)) == $prefix) {
                $sku->product->name = substr($sku->product->name, strlen($prefix));
            }

            $productCols = array('name', 'description', 'description2');
            foreach ($productCols as $col) {
                $newSku->product->{$col} = $sku->product->{$col};
            }

            $sku->product->name = $prefix.$sku->product->name;
            $sku->product->save();

            $skuCols = array('client_sku', 'sku_weight');
            foreach ($skuCols as $col) {
                $newSku->{$col} = $sku->{$col};
            }

            $newSku->save();

            foreach($newSku->product->media as $m) {
                $m->forceDelete();
            }

            foreach($sku->product->media as $m) {
                $pm = new ProductMedia;
                $pm->product_id = $newSku->product->id;
                $pm->media_id = $m->media_id;
                $pm->sort_order = $m->sort_order;
                $pm->save();

                if($m->sort_order == 1) {
                    $newSku->product->default_media = $pm->id;
                }
            }

            $newSku->product->save();
        }
	}
}
