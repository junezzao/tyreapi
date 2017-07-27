<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Admin\Product;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateElasticSearch extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    private $product_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($product_id)
    {
        $this->product_id = $product_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $product = Product::with('sku_in_channel', 'media', 'default_media', 'Essku_in_channel', 'brand', 'tags', 'merchant','category')->find($this->product_id);
        if (!is_null($product)) Product::insertProduct($product->toArray());
    }
}
