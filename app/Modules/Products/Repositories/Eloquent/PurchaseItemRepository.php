<?php
namespace App\Modules\Products\Repositories\Eloquent;

use App\Modules\Products\Repositories\Contracts\PurchaseItemRepositoryContract as PurchaseItemRepositoryInterface;
use App\Modules\Products\Repositories\Eloquent\PurchaseRepository as PurchaseRepo;
use App\Repositories\Repository as Repository;
use App\Repositories\Eloquent\SKUOptionRepository as SKUOptionRepo;
use App\Repositories\Eloquent\SKUCombinationRepository as SKUCombinationRepo;
use App\Repositories\ProductRepository as ProductRepo;
use App\Repositories\SKURepository as SKURepo;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\Eloquent\ProductTagRepository as TagRepo;
use App\Repositories\ChannelRepository as ChannelRepo;
use App\Modules\Channels\Repositories\Eloquent\ChannelTypeRepository as ChannelTypeRepo;

use App\Models\Admin\PurchaseItem;
use App\Models\Admin\Purchase;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;

use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;

class PurchaseItemRepository extends Repository implements PurchaseItemRepositoryInterface
{
    protected $model;

    protected $purchaseRepo;

    protected $role;

    protected $skipCriteria = false;

    protected $user_id;

    public function __construct(PurchaseItem $model)
    {
        $this->model = $model;
        $this->purchaseRepo = new PurchaseRepo(new Purchase);
        $this->user_id = Authorizer::getResourceOwnerId();
        
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\PurchaseItem';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = [
           'batch_id'  => 'required|integer|exists:purchase_batches',
           'sku_id' => 'required|integer|exists:sku',
           'item_cost' => 'required|numeric|min:0.01',
           'item_quantity' => 'required|integer|min:0',
           'item_retail_price' => 'required|numeric|min:0.00'
        ];

        $messages = [];
        
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
   
        $data = $newinputs;
        unset($data['replenishment']);
        $model = parent::create($data);
        Activity::log('Procurement '.$data['batch_id'].' item '.$model->item_id.' created', $this->user_id);
        
        return $model;
    }

    public function update(array $data, $id, $attribute='item_id')
    {
        // Inputs validations
        $item = $this->findOrFail($id);
        $batch = $this->purchaseRepo->findOrFail($item->batch_id);
        $data['batch_status'] = $batch->batch_status;
        $rules = [
            'batch_status' => 'not_in:1',
            'sku_id' => 'sometimes|required|integer|exists:sku',
            'item_cost' => 'sometimes|required|numeric|min:0.01',
            'item_quantity' => 'sometimes|required|integer|min:0',
            'item_retail_price' => 'sometimes|required|numeric|min:0.00',
        ];
        
        $messages = array('batch_status.not_in'=>'The selected procurement already set to Received.');
        
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        
        unset($data['batch_id'], $data['batch_status']);
        
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        $model = parent::update($data, $id, $attribute);
        Activity::log('Procurement '.$batch->batch_id.' item '.$id.' updated', $this->user_id);
        return $this->find($id);
    }

    public function delete($id)
    {
        $item = $this->with('sku')->findOrFail($id);
        $batch = $this->purchaseRepo->findOrFail($item->batch_id);
        $data['batch_status'] = $batch->batch_status;
        $rules = [
            'batch_status' => 'not_in:1',
        ];
        $messages = array('batch_status.not_in'=>'The selected procurement already set to Received.');
        
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        // delete product that are created through procurements
        if($batch->replenishment == 0){
            // will have to wipe all the created products, skus, etc.
            $channelTypeRepo = new ChannelTypeRepo(new ChannelType);
            $channelType = $channelTypeRepo->where('name','=','Warehouse')->first();
            $channelRepo = new ChannelRepo(new Channel);
            $warehouse = $channelRepo->where('channel_type_id','=',$channelType->id)
                ->where('merchant_id','=', $batch->merchant_id)
                ->first();

            $productIds = array();
            \DB::beginTransaction();
            if ($item->sku) {
                if (!in_array($item->sku->product->id, $productIds)) {
                    $productIds[] = $item->sku->product->id;
                }
                // check whether sku is already being transfer accross channels
                if(!empty($warehouse)) {
                    $channelSkuRepo = new ChannelSKURepo;
                    $channel_sku = $channelSkuRepo->where('sku_id','=',$item->sku->sku_id)
                                    ->where('channel_id','!=',$warehouse->id)->all();
                    $tmp  =  $channel_sku->toArray();
                    if(empty($tmp)) {
                        $skuRepo = new SKURepo;
                        $sku = $skuRepo->find($item->sku->sku_id);
                        //\Log::info(print_r($sku->toArray(),true));
                        $skuCombinationRepo = new SKUCombinationRepo;
                        $skuCombo = $skuCombinationRepo->where('sku_id','=',$sku->sku_id)->all();
                        foreach($skuCombo as $combo){
                            $combo->delete();
                        }
                        // delete from all channel (warehouse) ChannelSKU
                        $sku->forceDelete();
                        $channelSkus = $channelSkuRepo->where('sku_id','=',$sku->sku_id)->all();
                        foreach($channelSkus as $channelSku){
                            $channelSku->forceDelete();
                        }
                    }
                }
                $item->sku->delete();
            }
            //clear all products without skus
            if (isset($productIds)) {
                foreach ($productIds as $productId) {
                    $product = Product::with('sku')->find($productId);

                    if (!is_null($product) && $product->sku->count() == 0) {
                        $product->forceDelete();
                    }

                    $tagsRepo = new TagRepo;
                    $tags = $tagsRepo->where('product_id','=',$productId)->withTrashAll();

                    foreach ($tags as $tag) {
                        $tag->forceDelete();
                    }
                }
            }
            \DB::commit();
        }
        $model = $item->delete();
        Activity::log('Procurement '.$batch->batch_id.' item '.$id.' deleted', $this->user_id);
        return $model;
    }

    public function upload($batch, $inputs)
    {
        foreach($inputs['items'] as $item)
        {
            $item['batch_id'] = $batch->batch_id;
            $item['merchant_id'] = $batch->merchant_id;
                
            if($inputs['replenishment']!==1)
            {
                if (strcasecmp($item['product'], 'NEW') == 0 || $item['product'] === 0)
                {
                    $productRepo = new $this->productRepo;
                    $brand = $this->brandRepo->where('prefix','=',$item['prefix'])
                                ->where('merchant_id','=', $inputs['merchant_id'])->first();
                    $item['brand_id'] = $brand->id?$brand->id:null;
                    $item['brand'] = $item['prefix'];
                    $item['merchant_id'] = $inputs['merchant_id'];
                    unset($item['prefix']);
                    $product = $productRepo->create($item);
                    //tags
                    foreach($item['tags'] as $tag)
                    {
                        $tag = new $this->tagRepo();
                        $tag->create(['value'=>$tag,'product_id'=>$product->id]);
                    }
                }
                elseif($item['product'] != '')
                {
                    $product = $this->productRepo->find( $item['product'] );
                }
                $item['product_id'] = $product->id;
                // create sku under the product
                $sku = $this->skuRepo->create($item);
                $item['sku_id'] = $sku->sku_id;
                foreach($item['option_name'] as $k => $v)
                {
                    //options
                    $optionRepo = new $this->skuOptionRepo;
                    $option = $optionRepo->create(['option_name'=>$v,'option_value'=>$item['option_value'][$k]]);
                    //combinations
                    $combinationRepo = new $this->skuCombinationRepo;
                    $combination = $combinationRepo->create(['option_id'=>$option->option_id,'sku_id'=>$sku->sku_id]);
                }

                $sku->update( ['hubwire_sku'=>!empty($item['client_sku'])?$item['client_sku']:$this->HWSKU($sku->sku_id)] );
            }
            
            $purchase_item = new $this->itemRepo(new PurchaseItem);
            $purchase_item->create($item);
        }
        Activity::log('Procurement '.$batch->batch_id.' item(s) uploaded', $this->user_id);
    
    }

    public function clear($batch_id)
    {
        $batch = $this->purchaseRepo->findOrFail($batch_id);
        $data['batch_status'] = $batch->batch_status;
        $rules = [
            'batch_status' => 'not_in:1',
        ];
        $messages = array('batch_status.not_in'=>'The selected procurement already set to Received.');
        
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $purchase_items = $this->with('sku')->where('batch_id','=',$batch_id)->all();

        if($batch->replenishment == 0){
            // will have to wipe all the created products, skus, etc.
            $channelTypeRepo = new ChannelTypeRepo(new ChannelType);
            $channelType = $channelTypeRepo->where('name', '=', 'Warehouse')->first();
            $channelRepo = new ChannelRepo(new Channel);
            $warehouse = $channelRepo->where('channel_type_id', '=', $channelType->id)
                                        ->find($batch->channel_id);

            $productIds = array();
            \DB::beginTransaction();
            foreach($purchase_items as $pi)
            {
                if ($pi->sku) {
                    if (!in_array($pi->sku->product->id, $productIds)) {
                        $productIds[] = $pi->sku->product->id;
                    }
                    // check whether sku is already being transfer accross channels
                    if(!empty($warehouse)) {
                        $channelSkuRepo = new ChannelSKURepo;
                        $channel_sku = $channelSkuRepo->where('sku_id','=',$pi->sku->sku_id)
                                        ->where('channel_id', '!=', $warehouse->id)->all();
                        $tmp  =  $channel_sku->toArray();
                        if(empty($tmp)) {
                            $skuRepo = new SKURepo;
                            $sku = $skuRepo->find($pi->sku->sku_id);
                            //\Log::info(print_r($sku->toArray(),true));
                            $skuCombinationRepo = new SKUCombinationRepo;
                            $skuCombo = $skuCombinationRepo->where('sku_id','=',$sku->sku_id)->all();
                            foreach($skuCombo as $combo){
                                $combo->delete();
                            }
                            // delete from all channel (warehouse) ChannelSKU
                            $sku->forceDelete();
                            $channelSkus = $channelSkuRepo->where('sku_id','=',$sku->sku_id)->all();
                            foreach($channelSkus as $channelSku){
                                $channelSku->forceDelete();
                            }
                        }
                    }
                    $pi->sku->delete();
                }
                $pi->delete();
            }
            //clear all products without skus
            if (isset($productIds)) {
                foreach ($productIds as $productId) {
                    $product = Product::with('sku')->find($productId);

                    if (!is_null($product) && $product->sku->count() == 0) {
                        $product->forceDelete();
                    }

                    $tagsRepo = new TagRepo;
                    $tags = $tagsRepo->where('product_id', '=', $productId)->withTrashAll();

                    foreach ($tags as $tag) {
                        $tag->forceDelete();
                    }
                }
            }
            \DB::commit();
        }else{
            foreach($purchase_items as $pi)
            {
                $pi->delete();
            }
        }
        Activity::log('Procurement '.$batch_id.' item(s) cleared', $this->user_id);
    }
}
