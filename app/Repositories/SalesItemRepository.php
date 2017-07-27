<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\ChannelSKURepositories as ChannelSKURepo;

class SalesItemRepository extends Repository
{
    protected $maps = [
            'sku_id' => 'sku_id',
            'price' => 'item_price',
            'discount' => 'item_discount',
            'quantity' => 'item_quantity',
            'tax' => 'item_tax',
            'tax_inclusive' => 'tax_inclusive'
            
    ];

    public function model()
    {
        return '\SalesItem';
    }
   
    public function create(array $inputs)
    {
        // Inputs validations
        $v = \Validator::make($inputs, [
            'sale_id' => 'required|integer|min:1|exists:sales,sale_id',
            'sku_id' => 'required|integer|min:1|exists:channel_sku,sku_id,channel_id,'.$inputs['channel_id'],
            'price' => 'required|numeric|min:0',
            'discount' => 'sometimes|required|numeric|min:0',
            'quantity' => 'sometimes|required|integer|min:1',
            'product_type' => 'required|in:ChannelSKU,PromoCode',
            'tax' => 'sometimes|required|numeric|min:0',
            'tax_inclusive' => 'sometimes|required|boolean'
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($inputs as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $inputs = $newinputs;
        $channel_sku = \ChannelSKU::where('sku_id', '=', $inputs['sku_id'])
                        ->where('channel_id', '=', $inputs['channel_id'])->first();
        $inputs['product_id'] = $channel_sku->channel_sku_id;
        $item = parent::create($inputs);
        return $this->find($item->item_id);
    }

    
    public function update(array $data, $id, $attribute="item_id")
    {
        // Inputs validations

        // \Log::info(print_r($data, true));

        $v = \Validator::make($inputs, [
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $item = parent::update($data, $id, $attribute);
        return $this->find($id);
    }
}
