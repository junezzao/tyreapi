<?php namespace App\Repositories\Criteria\Products;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Criteria;

class ByChannel extends Criteria
{
    
    private $channel_id;
    
    public function __construct($channel_id)
    {
        $this->channel_id = $channel_id;
    }
    
    /**
     * @param $model
     * @param RepositoryContract $repository
     * @return mixed
     */
    public function apply($model, RepositoryContract $repository)
    {
        $model->by_channel = true;
        $query = $model->with([
                        'sku_in_channel' => function ($q) {
                            $q->where('channel_sku.channel_id', '=', $this->channel_id);
                        },
                        'product_category' => function($q){
                            $q->where('product_third_party_categories.channel_id','=',$this->channel_id);
                        }
                    ])
                    ->whereHas('channel_sku', function ($query) {
                        $query->where('channel_id', '=', $this->channel_id);
                    });
                    // ->groupBy('products.product_id');
        return $query;
    }
}
