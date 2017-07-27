<?php namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;

use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\ProductThirdParty;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\Purchase;
use App\Models\Admin\Product;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;

use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use Carbon\Carbon;

class SyncRepository extends Repository
{
    protected $model;

    protected $role;

    protected $skipCriteria = true;

    protected $user_id;


    public function __construct()
    {
        parent::__construct();
    }

    public function model()
    {
        return 'App\Models\Admin\ThirdPartySync';
    }

    public function create(array $data)
    {
        $data['sync_type'] = empty($data['sync_type']) ? 'auto' : $data['sync_type'];

        $channel = Channel::findOrFail($data['channel_id']);
        $data['channel_type_id'] = $channel->channel_type_id;

        $channel_type = ChannelType::find($data['channel_type_id']);
        if(intval($channel_type->third_party) !== 1) return false;

        $re = "/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/";
        $a = preg_split($re, $data['action']);
        $data['trigger_event'] = !empty($data['trigger_event'])?$data['trigger_event']:implode(' ', array_map("ucfirst", $a)) .sprintf(' #%06d',$data['ref_table_id']);

        $data['status'] = 'NEW';

        // Inputs validations
        $rules = [
            'channel_id' => 'required|exists:channels,id',
            'channel_type_id' => 'required',
            'ref_table' => 'required',
            'ref_table_id' => 'required|integer',
            'action' => 'required',
            'sync_type' => 'required',
            'extra_info' => 'sometimes',
            'trigger_event' => 'required',
            'request_id' => 'sometimes|required',
            'status' => 'required',
            'remarks' => 'sometimes|required',
            'sent_time' => 'sometimes|required',
            'merchant_id' => 'sometimes|required|exists:merchants,id'
        ];

        $messages = [];

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            $errors =  [
                'code' =>  422,
                'error' => $v->errors()
            ];
            return $errors;
        }

        $exists = $this->where('action','=',$data['action'])
                    ->where('ref_table','=',$data['ref_table'])
                    ->where('ref_table_id','=',$data['ref_table_id'])
                    ->where('channel_id','=',$data['channel_id']);

        if (strcasecmp($data['action'], 'createProduct') == 0 || strcasecmp($data['action'], 'createSKU') == 0) {
            $exists = $exists->first();
        }
        else {
            $exists = $exists->where('status','=','NEW')->first();
        }

        if (!is_null($exists)) {
            if (strcasecmp($data['action'], 'createSKU') == 0) {
                if ($exists->status == 'FAILED') {
                    $exists->status = 'RETRY';
                    $exists->save();
                }

                return $exists;
            }

            return false;
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
        $model = parent::create($data);
        // \Log::info(print_r($model, true));
        \Log::info('New sync created!');

        // Activity::log('Sync '.$model->sync_id.' was created', $this->user_id);

        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='sync_id')
    {
        // Inputs validations

        $rules = [
            'channel_id' => 'sometimes|required|exists:channels,id',
            'channel_type' => 'sometimes|required',
            'ref_table' => 'sometimes|required',
            'ref_table_id' => 'sometimes|required|integer',
            'action' => 'sometimes|required',
            'sync_type' => 'sometimes|required',
            'extra_info' => 'sometimes',
            'trigger_event' => 'sometimes|required',
            'request_id' => 'sometimes|required',
            'status' => 'sometimes|required',
            'remarks' => 'sometimes|required',
            'sent_time' => 'sometimes|required',
            'merchant_id' => 'sometimes|required|exists:merchants,id'
        ];
        $messages = array();

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            $errors =  [
                'code' =>  422,
                'error' => $v->errors()
            ];
            return $errors;
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
        $updated = parent::update($data, $id, $attribute);
        $model = $this->find($id);
        // Activity::log('Sync ('.$id.') was updated', $this->user_id);
        return $model;
    }

    public function createProduct($input = null){
        $created = false;

        if ($input == null) {
            $input['channel_id'] = request()->get('channel_id');
            $input['product_id'] = request()->get('product_id');
        }

        $product = Product::findOrFail($input['product_id']);
        $product_third_party = ProductThirdParty::where('product_id','=',$input['product_id'])
                                                    ->where('channel_id','=',$input['channel_id'])
                                                    ->first();

        if (is_null($product_third_party)) {
            //only create product if product does not exist in 3rd party
            $input['action'] = __FUNCTION__;
            $input['ref_table'] = 'Product';
            $input['ref_table_id'] = $product->id;
            $input['merchant_id'] = $product->merchant_id;
            $created = $this->create($input);
        }

        return $created;
    }

    public function updateProduct($input = null){
        $created = false;

        if ($input == null) {
            $input['channel_id'] = request()->get('channel_id', '');
            $input['product_id'] = request()->get('product_id');
        }

        $product = Product::findOrFail($input['product_id']);
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Product';
        $input['ref_table_id'] = $product->id;
        $input['merchant_id'] = $product->merchant_id;

        if (isset($input['channel_id']) && $input['channel_id'] !== "") {
            // If channel_id is stated , updateProduct only for that channel id
            $product_third_party = ProductThirdParty::where('product_id','=',$input['product_id'])
                                                        ->where('channel_id','=',$input['channel_id'])
                                                        ->first();

            if (!is_null($product_third_party)) {
                $created = $this->create($input);
            }
        }
        else {
            // Update product in all channels
            $product_third_party = ProductThirdParty::where('product_id','=',$input['product_id'])->get();

            $create = [];
            foreach($product_third_party as $tp){
                if ($tp->ref_id !== 0 && $tp->ref_id !== null) {
                    $input['channel_id'] = $tp->channel_id;
                    $created[] = $this->create($input);
                }
            }
        }

        return $created;
    }

    /* Update All Products within Specific Marketplace Channel*/
    public function updateAllProducts($input){
        if (isset($input['channel_id'])) {
            $products = ProductThirdParty::where('channel_id','=',$input['channel_id'])->get();

            foreach($products as $product){
                if ($tp->ref_id !== 0 && $tp->ref_id !== null) {
                    $input['action'] = 'updateProduct';
                    $input['channel_id'] = $product->channel_id;
                    $input['ref_table'] = 'Product';
                    $input['ref_table_id'] = $product->product_id;
                    $input['trigger_event'] = 'Update All Products';
                    $this->create($input);
                }
            }
        }
    }

    public function updateMedia($input) {
        $product = Product::findOrFail($input['product_id']);

        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Product';
        $input['ref_table_id'] = $product->id;

        if (empty($input['channel_id'])) {
            $product_third_party = ProductThirdParty::where('product_id','=',$input['product_id'])->get();
            $responses = array();

            foreach($product_third_party as $tp){
                if ($tp->ref_id !== 0 && $tp->ref_id !== null) {
                    $input['channel_id'] = $tp->channel_id;
                    $input['merchant_id'] = $product->merchant_id;
                    $responses[] = $this->create($input);
                }
            }

            return $responses;
        }
        else {
            $product_third_party = ProductThirdParty::where('product_id', '=', $input['product_id'])
                                                        ->where('channel_id', '=', $input['channel_id'])
                                                        ->first();

            if (!empty($product_third_party) && $product_third_party->ref_id !== 0 && $product_third_party->ref_id !== null) {
                $input['merchant_id'] = $product->merchant_id;
                return $this->create($input);
            }
        }
    }

    public function updateSKU($input){
        $channel_sku = ChannelSKU::findOrFail($input['channel_sku_id']);
        $product = Product::findOrFail($channel_sku->product_id);

        $productThirdParty = ProductThirdParty::where('product_id', '=', $product->id)
                                                        ->where('channel_id', '=', $channel_sku->channel_id)
                                                        ->first();
        if (!is_null($productThirdParty)) {
            $input['action'] = __FUNCTION__;
            $input['channel_id'] = $channel_sku->channel_id;
            $input['ref_table'] = 'ChannelSKU';
            $input['merchant_id'] = $product->merchant_id;
            $input['ref_table_id'] = $channel_sku->channel_sku_id;
            if($channel_sku->ref_id == null || $channel_sku->ref_id == 0){
                $input['action'] = 'createSKU';
            }
            return $this->create($input);
        }
        else {
            return array('error' => "Product not yet in marketplace.");
        }
    }

    public function updateChannelSKU($input){
        $input['action'] = 'updateSKU';
        $channel_sku = ChannelSKU::findOrFail($input['channel_sku_id']);
        $product = Product::findOrFail($channel_sku->product_id);

        $input['channel_id'] = $channel_sku->channel_id;
        $input['ref_table'] = 'ChannelSKU';
        $input['ref_table_id'] = $channel_sku->channel_sku_id;
        $input['merchant_id'] = $product->merchant_id;

        if($channel_sku->ref_id == null  || $channel_sku->ref_id == 0){
            $input['action'] = 'createSKU';
        }
        $this->create($input);
    }

    public function updatePrice($input){
        $input['action'] = 'updatePrice';
        $channel_sku = ChannelSKU::findOrFail($input['channel_sku_id']);
        $product = Product::findOrFail($channel_sku->product_id);

        $input['channel_id'] = $channel_sku->channel_id;
        $input['ref_table'] = 'ChannelSKU';
        $input['ref_table_id'] = $channel_sku->channel_sku_id;
        $input['merchant_id'] = $product->merchant_id;
        $this->create($input);
    }

    public function updateQuantity($input){
        $input['action'] = __FUNCTION__;
        $channel_sku = ChannelSKU::findOrFail($input['channel_sku_id']);
        $product = Product::findOrFail($channel_sku->product_id);

        if($channel_sku->ref_id !== null && $channel_sku->ref_id > 0){
            $input['action'] = 'updateQuantity';
            $input['channel_id'] = $channel_sku->channel_id;
            $input['merchant_id'] = $product->merchant_id;
            $input['ref_table'] = 'ChannelSKU';
            $input['ref_table_id'] = $channel_sku->channel_sku_id;
            $this->create($input);
        }
    }

    public function stockTransfer($do_id) {
        $delivery_order = DeliveryOrder::with('items')->withTrashed()->findOrFail($do_id);
        $target_channel = Channel::with('channel_type')->findOrFail($delivery_order->target_channel_id);
        $originating_channel = Channel::with('channel_type')->find($delivery_order->originating_channel_id);

        $input['trigger_event'] = 'Stock Transfer' . sprintf(' #%06d',$do_id);
        $input['merchant_id'] = $delivery_order->merchant_id;

        // after stock transfer is initiated
        if($delivery_order->do_type == 2 && $delivery_order->status == 1)
        {
            foreach ($delivery_order->items as $item) {
                $item_origin = ChannelSKU::where('channel_sku_id', '=', $item->channel_sku_id)->first();
                $originating_channel = Channel::with('channel_type')->findOrFail($item_origin->channel_id);
                if($originating_channel->channel_type->third_party == 1)
                {
                    $input['channel_id'] = $originating_channel->id;
                    $input['action'] = 'updateQuantity';
                    $input['ref_table'] = 'ChannelSKU';
                    $input['ref_table_id'] = $item_origin->channel_sku_id;

                    $this->create($input);
                }
            }
        }
        elseif ($originating_channel->channel_type->third_party == 1  && $delivery_order->status == 1) {
            foreach ($delivery_order->items as $item) {
                $item_origin = ChannelSKU::where('channel_id', '=', $originating_channel->id)->where('sku_id', '=', $item->channel_sku->sku->sku_id)->first();

                $input['channel_id'] = $originating_channel->id;
                $input['action'] = 'updateQuantity';
                $input['ref_table'] = 'ChannelSKU';
                $input['ref_table_id'] = $item_origin->channel_sku_id;

                $this->create($input);
            }
        }

        // after stock transfer is received
        if ($target_channel->channel_type->third_party == 1 && $delivery_order->status == 2 && is_null($delivery_order->deleted_at)) {
            foreach ($delivery_order->items as $item) {
                $item_target = ChannelSKU::where('channel_id', '=', $target_channel->id)->where('sku_id', '=', $item->channel_sku->sku->sku_id)->first();

                $input['channel_id'] = $target_channel->id;

                // check 3rd party details on the product
                $product_third_party = ProductThirdParty::where('product_id', '=', $item->channel_sku->sku->product_id)->where('channel_id', '=', $target_channel->id)->first();
                if(!is_null($product_third_party) && $product_third_party->ref_id != null && $product_third_party->ref_id != 0)
                {
                    // check 3rd party on sku/variant/options
                    if($item_target->ref_id == 0 || $item_target->ref_id == null)
                    {
                        // product doesn't exist in the 3rd party yet, create the product
                        $input['action'] = 'createSKU';
                        $input['ref_table'] = 'ChannelSKU';
                        $input['ref_table_id'] = $item_target->channel_sku_id;
                    }
                    else
                    { // its already there, just need to update the quantity
                        $input['action'] = 'updateQuantity';
                        $input['ref_table'] = 'ChannelSKU';
                        $input['ref_table_id'] = $item_target->channel_sku_id;
                    }

                    $this->create($input);
                }
            }
        }
    }

    public function restock($batchId) {
        $batch = Purchase::with('items')->findOrFail($batchId);
        $channel = Channel::with('channel_type')->findOrFail($batch->channel_id);

        $responses = array();

        if ($channel->channel_type->third_party == 1) {
            foreach ($batch->items as $item) {
                $productThirdParty = ProductThirdParty::where('product_id', '=', $item->sku->product_id)
                                                        ->where('channel_id', '=', $channel->id)
                                                        ->first();

                if (!is_null($productThirdParty)) {
                    $channelSku = ChannelSKU::where('product_id', '=', $item->sku->product_id)
                                                ->where('sku_id', '=', $item->sku_id)
                                                ->where('channel_id', '=', $channel->id)
                                                ->first();

                    $input['channel_sku_id'] = $channelSku->channel_sku_id;

                    if($channelSku->ref_id == null || $channelSku->ref_id == 0) {
                        $responses[] = $this->updateSKU($input);
                    }
                    else {
                        $responses[] = $this->updateQuantity($input);
                    }
                }
            }
        }

        return $responses;
    }

    public function getDocument($input){
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'OrderItem';
        $input['ref_table_id'] = $input['item_id'];
        return $this->create($input);
    }

    public function readyToShip($input){
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $input['sale_id'];
        return $this->create($input);
    }

    public function createOrderFulfillment($input){
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Sales';
        $input['ref_table_id'] = $input['sale_id'];
        return $this->create($input);
    }

    /**
     * Shopify - Capture a previously authorized order for the full amount
     *
     * @param  array input
     */
    public function captureOrderPayment($input) {
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $input['sale_id'];
        $this->create($input);
    }

    /**
     * Shopify - To create refund for an item from hubwire and sync to Shopify
     *
     * @param  array input
     */
    public function createItemRefund($input) {
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'OrderItem';
        $input['ref_table_id'] = $input['item_id'];

        $order_item = OrderItem::findOrFail($input['item_id']);
        $channel_sku = ChannelSKU::findOrFail($order_item->ref_id);
        $product = Product::findOrFail($channel_sku->product_id);
        $input['merchant_id'] = $product->merchant_id;
        $input['extra_info'] = json_encode(array('restock'=>$input['restock']));
        $this->create($input);
    }

    /**
     * Shopify - To create shipping refund
     *
     * @param  array input
     */
    public function createShippingRefund($input) {
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $input['sale_id'];
        $this->create($input);
    }

    /**
     * Shopify - Cancel an Order
     *
     * @param  array input
     */
    public function cancelOrder($input) {
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $input['sale_id'];
        $this->create($input);
    }

    public function orderCreated($order_id)
    {
        $order = Order::find($order_id);
        $input = array();
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $order_id;
        $input['channel_id'] = $order->channel_id;
        $this->create($input);
    }

    public function orderUpdated($order_id)
    {
        $order = Order::find($order_id);
        $input = array();
        $input['action'] = __FUNCTION__;
        $input['ref_table'] = 'Order';
        $input['ref_table_id'] = $order_id;
        $input['channel_id'] = $order->channel_id;
        $response = $this->create($input);
        // \Log::info(print_r($response, true));
    }
}
