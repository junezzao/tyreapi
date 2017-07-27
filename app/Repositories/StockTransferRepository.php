<?php
namespace app\Repositories;

use App\Repositories\Contracts\StockTransferRepositoryContract;
use App\Repositories\Contracts\PartnerRepositoryContract;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\DeliveryOrderItem;
use App\Models\Admin\GTOManifest;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use App\Events\ChannelSkuQuantityChange;
use App\Events\StockTransferCreated;


class StockTransferRepository implements StockTransferRepositoryContract
{

    protected $user_id;

    public function __construct()
    {
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    // create draft stock transfer
    public function createStockTransfer($inputs)
    {
        
        \DB::beginTransaction();

        // construct sku list
        $sku_list = array();
        foreach ($inputs['skuid'] as $key => $value) {
            $sku_list[] = ['product_id'=>$inputs['prodid'][$key],'channel_sku_id'=>$key, 'sku_id'=>$value,'quantity'=>$inputs['channel_sku_quantity'][$key]];
        }
        if (!empty($sku_list) && is_array($sku_list)) {
            $rules = [];
            foreach ($sku_list as $key => $value) {
                if($inputs['do_type'] == 2)
                {
                    $rules['sku_list.'.$key.'.product_id'] = 'exists:products,id,merchant_id,'.$inputs['merchant_id'];         
                    $rules['sku_list.'.$key.'.channel_sku_id'] = 'exists:channel_sku,channel_sku_id,product_id,'.$value['product_id'];         
                }
                else
                    $rules['sku_list.'.$key.'.sku_id'] = 'exists:channel_sku,sku_id,channel_id,'.$inputs['originating_channel_id'];
                    
                $channel_sku = ChannelSKU::find($value['channel_sku_id']);
                if (!is_null($channel_sku)) {
                    $rules['sku_list.'.$key.'.quantity'] = 'required|integer|min:1|max:'.$channel_sku->channel_sku_quantity;
                }
            }
        }
        $input = ['sku_list' => $sku_list];
        $v = \Validator::make($input, $rules);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        // check if duplicate stock transfer
        $do = DeliveryOrder::where('originating_channel_id', '=', $inputs['originating_channel_id'])
                            ->where('target_channel_id', '=', $inputs['target_channel_id'])
                            ->where('merchant_id','=',$inputs['merchant_id'])
                            ->where('status', '=', 0)
                            ->orderBy('id', 'desc')
                            ->get();

        if( !$do->isEmpty() ){
            foreach($do as $d){
                $ret = 0;
                foreach($d->items as $item){
                    foreach($inputs['channel_sku_quantity'] as $channel_sku_id => $quantity){
                        if($item->channel_sku_id == $channel_sku_id && $item->quantity == $quantity){
                            $ret++;
                        }
                    }
                }
                if($ret == count($d->items) && $ret == count($inputs['channel_sku_quantity'])){
                    $errors =  response()->json(
                    array(
                        'code' =>  422,
                        'error' => ["duplicate"=>'A duplicate submission was found. If this was intentional, please check that the first transfer has been received before submitting the second transfer request.']
                    ));
                    throw new HttpResponseException($errors);
                }
            }
        }

        $response = new \stdClass();
        $do = new DeliveryOrder;
        $do->do_type = $inputs['do_type'];
        $do->remarks = $inputs['remarks'];
        $do->merchant_id = $inputs['merchant_id'];
        $do->originating_channel_id = !empty($inputs['originating_channel_id'])?$inputs['originating_channel_id']:NULL;
        $do->target_channel_id = $inputs['target_channel_id'];
        $do->status = 0;//$inputs['do_status'];
        $do->draft_at = date('Y-m-d H:i:s');
        $do->person_incharge = $inputs['pic'];
        $do->lorry_no =  $inputs['lorry_no'];
        $do->transport_co =  $inputs['transport_co'];
        $do->driver_name =  $inputs['driver_name'];
        $do->driver_id =  $inputs['driver_id'];
        $do->user_id = $this->user_id;
        if ($do->do_type==0)
            $do->batch_id = $inputs['batch_id'];
        $do->save();

        foreach ($sku_list as $key => $value) 
        {
            $item = new DeliveryOrderItem;
            $item->channel_sku_id = $value['channel_sku_id'];
            $item->do_id = $do->id;
            $item->sku_id = $value['sku_id'];
            $item->quantity = $value['quantity'];
            $item->created_at = $do->created_at;
            $item->status = 0;
            $item->save();
            if($do->do_type==2)
            {
                $o_channel_sku = ChannelSKU::find($item->channel_sku_id);
                event(new ChannelSkuQuantityChange($o_channel_sku->channel_sku_id, $item->quantity, 'DeliveryOrder', $do->id, 'decrement'));
            }
        }
        if($do->do_type == 2)
        {
            $do->status = 1;
            $do->sent_at = date('Y-m-d H:i:s');
            $do->save();
        }
        
        Activity::log('Stock transfer ('.$do->id.') was successfully created.', $this->user_id);
        event(new StockTransferCreated($do));

        \DB::commit();
        

        return $this->apiResponse($do->id);
    }

    public function updateStockTransfer($inputs, $id)
    {
        \DB::beginTransaction();

        // construct sku list
        $sku_list = array();
        foreach ($inputs['skuid'] as $key => $value) {
            $sku_list[] = ['channel_sku_id'=>$key, 'sku_id'=>$value,'quantity'=>$inputs['channel_sku_quantity'][$key]];
        }
        if (!empty($sku_list) && is_array($sku_list)) {
            $rules = [];
            foreach ($sku_list as $key => $value) {
                $rules['sku_list.'.$key.'.sku_id'] = 'exists:channel_sku,sku_id,channel_id,'.$inputs['originating_channel_id'];
                $channel_sku = ChannelSKU::find($value['channel_sku_id']);
                if (!is_null($channel_sku)) {
                    $rules['sku_list.'.$key.'.quantity'] = 'integer|min:1|max:'.$channel_sku->channel_sku_quantity;
                }
            }
        }
        $input = ['sku_list' => $sku_list];
        $v = \Validator::make($input, $rules);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $do = DeliveryOrder::find($id);

        $do->do_type = $inputs['do_type'];
        $do->remarks = $inputs['remarks'];
        $do->target_channel_id = $inputs['target_channel_id'];
        $do->status = 0;//$inputs['do_status'];
        $do->person_incharge = $inputs['pic'];
        $do->lorry_no =  $inputs['lorry_no'];
        $do->transport_co =  $inputs['transport_co'];
        $do->driver_name =  $inputs['driver_name'];
        $do->driver_id =  $inputs['driver_id'];
        $do->user_id = $this->user_id;
        if ($do->do_type==0)
            $do->batch_id = $inputs['batch_id'];
        $do->save();

        // remove all delivery order items for that DO
        DeliveryOrderItem::where('do_id', '=', $do->id)->delete();

        // recreate DO Items
        foreach ($sku_list as $key => $value) {
            $item = new DeliveryOrderItem;
            $item->channel_sku_id = $value['channel_sku_id'];
            $item->do_id = $do->id;
            $item->sku_id = $value['sku_id'];
            $item->quantity = $value['quantity'];
            $item->created_at = $do->created_at;
            $item->status = 0;
            $item->save();
        }

        \DB::commit();
        Activity::log('Stock transfer ('.$do->id.') was successfully updated.', $this->user_id);

        return $this->apiResponse($do->id);
    }

    public function initiateTransfer($id)
    {
        \DB::beginTransaction();
        $do = DeliveryOrder::with('items')->findOrFail($id);

        if ($do->status==0) {
            $quantityOk = true;
            $originChannelOk = true;
            $targetChannelOk = true;
            $errors = [];

            // check that all channel skus have enough quantity
            foreach ($do->items as $item) {
                $o_channel_sku = ChannelSKU::with('sku', 'product')->find($item->channel_sku_id);

                if ($o_channel_sku->channel_sku_quantity<$item->quantity) {
                    $errors[] = $o_channel_sku->product->name.' ('.$o_channel_sku->sku->hubwire_sku.') does not have enough quantity.';
                    $quantityOk = false;
                }
            }

            // check that the originating channel is selling under that merchant
            if($do->do_type !== 2)
            {
                $rel = \DB::table('channel_merchant')->where('channel_id', '=', $do->originating_channel_id)
                    ->where('merchant_id', '=', $do->merchant_id)->first();
                if (is_null($rel)) {
                    $errors["o_channel"] = "The merchant is no longer selling under the originating channel. Please ensure that the merchant is selling under that channel before proceeding.";
                    $originChannelOk = false;
                }
            }

            $rel = \DB::table('channel_merchant')->where('channel_id', '=', $do->target_channel_id)
                ->where('merchant_id', '=', $do->merchant_id)->first();
            if (is_null($rel)) {
                $errors["t_channel"] = "The merchant is no longer selling under the target channel. Please ensure that the merchant is selling under that channel before proceeding.";
                $targetChannelOk = false;
            }

            if (!$quantityOk || !$originChannelOk || !$targetChannelOk) {
                $err =  response()->json(
                 array(
                    'code' =>  422,
                    'error' => $errors
                ));
                throw new HttpResponseException($err);
            }

            $chSkus = [];
            $recipient_ch_id = $do->target_channel_id;

            foreach ($do->items as $item) {

                $o_channel_sku = ChannelSKU::find($item->channel_sku_id);
                // for stock transfer quantity log event
                $chSkus[] = ["ref_table"=>"DeliveryOrder", "ref_table_id"=>$id, "channel_sku_id"=>$o_channel_sku->channel_sku_id, "quantity_old"=>$o_channel_sku->channel_sku_quantity, "quantity_new"=>$o_channel_sku->channel_sku_quantity-$item->quantity];
                // $oldQuantity = $o_channel_sku->channel_sku_quantity;
                // $o_channel_sku->decrement('channel_sku_quantity', $item->quantity);
                // $o_channel_sku->touch();
                // event(new ChannelSkuQuantityChange($o_channel_sku->channel_sku_id, $oldQuantity, 'DeliveryOrder', $do->id));
                event(new ChannelSkuQuantityChange($o_channel_sku->channel_sku_id, $item->quantity, 'DeliveryOrder', $do->id, 'decrement'));
            }
            $do->status = 1;
            $do->sent_at = date('Y-m-d H:i:s');
            $do->save();
            \DB::commit();
            Activity::log('Stock transfer ('.$id.')  was sent.', $this->user_id);

            return ['stockTransfer'=>$this->apiResponse($id), 'skus'=>$chSkus];
        }
        else
            return null;
    }

    public function receiveStockTransfer($id)
    {
        \DB::beginTransaction();
        $do = DeliveryOrder::with('items')->findOrFail($id);

        // if stock transfer is in transit
        if ($do->status==1) {

            $chSkus = [];
            $targetChannel = Channel::findOrFail($do->target_channel_id);

            // 
            if ($targetChannel->status == 'Inactive') {
                $err =  response()->json(
                 array(
                    'code' =>  422,
                    'error' => ["t_channel" => "The target channel is inactive. Please ensure the channel is active before receiving the transfer."]
                ));
                throw new HttpResponseException($err);
            }
            
            foreach ($do->items as $item) {
                $t_channel_sku = ChannelSKU::where('sku_id', '=', $item->sku_id)
                                ->where('channel_id', '=', $do->target_channel_id)->first();
                if (is_null($t_channel_sku)) {
                    $o_channel_sku = ChannelSKU::with('sku', 'product')->find($item->channel_sku_id);
                    $t_channel_sku = new ChannelSKU;
                    $t_channel_sku->sku_id = $o_channel_sku->sku_id;
                    $t_channel_sku->channel_id = $do->target_channel_id;
                    $t_channel_sku->product_id = $o_channel_sku->product_id;
                    $t_channel_sku->merchant_id = $o_channel_sku->merchant_id;
                    $t_channel_sku->channel_sku_quantity = 0;
                    $t_channel_sku->channel_sku_price = $o_channel_sku->channel_sku_price;
                    $t_channel_sku->channel_sku_promo_price = $o_channel_sku->channel_sku_promo_price;
                    $t_channel_sku->channel_sku_active = $o_channel_sku->channel_sku_active;
                    $t_channel_sku->channel_sku_coordinates = $o_channel_sku->channel_sku_coordinates;
                    $t_channel_sku->save();
                }
                $chSkus[] = ["ref_table"=>"DeliveryOrder", "ref_table_id"=>$id, "channel_sku_id"=>$t_channel_sku->channel_sku_id, "quantity_old"=>$t_channel_sku->channel_sku_quantity, "quantity_new"=>$t_channel_sku->channel_sku_quantity+$item->quantity];
                // $oldQuantity = $t_channel_sku->channel_sku_quantity;
                // $t_channel_sku->increment('channel_sku_quantity', $item->quantity);
                // event(new ChannelSkuQuantityChange($t_channel_sku->channel_sku_id, $oldQuantity, 'DeliveryOrder', $do->id));
                // $t_channel_sku->touch();
                event(new ChannelSkuQuantityChange($t_channel_sku->channel_sku_id, $item->quantity, 'DeliveryOrder', $do->id, 'increment'));
            }
            $do->status = 2;
            $do->receive_at = date('Y-m-d H:i:s');
            $do->save();
            \DB::commit();
            Activity::log('Stock transfer ('.$id.')  was received.', $this->user_id);

            return ['stockTransfer'=>$this->apiResponse($id), 'skus'=>$chSkus];
        }
        else {
            return null;
        }
    }

    public function deleteStockTransfer($id)
    {
        $do  = DeliveryOrder::findOrFail($id);
        $rules = ['status' => 'integer|max:0'];
        $messages = ['status.max'=>'Can only delete stock transfer if it is a draft.'];

        $status = $do->status;
        $v = \Validator::make(['status'=>(int)$status], $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $ack = $do->delete();
        DeliveryOrderItem::where('do_id', $id)->delete();
        Activity::log('Stock transfer ('.$id.')  was deleted', $this->user_id);
        return $ack;
    }

    // delete batch item if stock transfer is still in draft mode
    public function deleteItem($doId, $itemId) {
        $do = DeliveryOrder::findOrFail($doId);
        $ack = false;
        if ($do->status==0) {
            $doItem = DeliveryOrderItem::find($itemId);
            $ack = $doItem->delete();
            Activity::log('Stock transfer item ('.$itemId.')  was deleted', $this->user_id);
        }
        return $ack;
    }

    /*public function getDistributionCenter($ch_id)
    {
        return \DistributionCenter::where('distribution_ch_id', '=', $ch_id)->first();
    }*/

    // return do + do items object
    public function apiResponse($id)
    {
        $response = new \stdClass();
        $do = DeliveryOrder::with('items')->findOrFail($id);
        $response->id = $do->id;
        $response->created_at = $do->created_at;
        $response->sent_at = $do->sent_at;
        $response->receive_at = $do->receive_at;
        $response->status = $do->status;
        $response->source_id = 0;
        $response->recipient_id = 0;
        $response->source_id = $do->originating_channel_id;
        $response->recipient_id = $do->target_channel_id;
        $items = $this->productAPIResponse($do->items);
        $response->sku_list = $items->products;
        $response->remarks = $do->remarks;
        return $response;
    }

    // return do items object
    public function productAPIResponse($items)
    {
        $response = new \stdClass();
        if (!empty($items)) {
            foreach ($items as $item) {
                $channel_sku = $item->channel_sku;
                $product = new \stdClass();
                $product->sku_id = $channel_sku['sku_id'];
                $product->product_name = addslashes($channel_sku->product->name);
                $product->hubwire_sku = $channel_sku->sku->hubwire_sku;
                $product->quantity = $channel_sku->channel_sku_quantity;
                $product->transfer_quantity = $item->quantity;
                $product->barcode = $channel_sku->sku->sku_barcode;
                $product->weight = $channel_sku->sku->sku_weight;
                $response->products[] = $product;
            }
        }
        return $response;
    }

    public function all($filters=array())
    {
        $stockTransfers = DeliveryOrder::select(\DB::raw('delivery_orders.*'));
        if(isset($filters['channel_id']) && !empty($filters['channel_id'])) {
            $stockTransfers = $stockTransfers->where('originating_channel_id', $filters['channel_id'])->orWhere('target_channel_id', $filters['channel_id']);
        }
        return $stockTransfers->get();
    }

    public function manifest($id)
    {
        $stockTransfer = DeliveryOrder::with('merchant')->find($id);
        $manifest = GTOManifest::where('do_id',$stockTransfer->id)->first();

        $response = new \stdClass();
        foreach($manifest->pickingItem as $item)
        {
            $options="";
            foreach($item->sku->combinations as $option){
                $options.=(!empty($options))?", ".$option->option_name.":".$option->option_value:''.$option->option_name.":".$option->option_value;
            }
            $tagsStr = '';
            $tags = $item->sku->product->tags()->get();
            
            if(!empty($tags)){
                foreach ($tags as $tag) {
                    $tagsStr .=(!empty($tagsStr))?', '.$tag->value:$tag->value;
                }
            }
            
            $tmp                    =   new \stdClass();
            $tmp->options           =   $options;
            $tmp->tags              =   $tagsStr; 
            $tmp->sku_id            =   $item->sku_id;
            $tmp->hubwire_sku       =   $item->sku->hubwire_sku;
            $tmp->brand_prefix      =   $item->sku->product->brands->prefix;
            $tmp->product           =   $item->sku->product->name;
            $tmp->options           =   $options;
            $tmp->picked            =   $item->picked;
            $tmp->merchant          =   $stockTransfer->merchant->name;
            $tmp->quantity          =   $item->quantity;
            $response->items[]      =   $tmp;
            
        }
        return $response;
    }
}
