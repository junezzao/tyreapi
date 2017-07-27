<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\OrderRepository as OrderRepositoryInterface;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Repositories\Repository as Repository;
use App\Repositories\Eloquent\SyncRepository;
use App\Repositories\Contracts\ChannelRepository;
use App\Events\ChannelSkuQuantityChange;
use App\Models\User;
use App\Models\Admin\PickingItem;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\OrderNote;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\Member;
use App\Models\Admin\Channel;
use App\Models\Admin\ThirdPartyReport;
use App\Models\Admin\ThirdPartyReportLog;
use Input;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\OrderHistory;
use Carbon\Carbon;
use Activity;
use App\Services\Mailer;
use Event;
use App\Events\OrderUpdated;
use App\Helpers\Helper;
use App\Exceptions\ValidationException as ValidationException;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Http\Traits\DocumentGeneration;
use Log;

class OrderRepository extends Repository implements OrderRepositoryInterface
{
    use DocumentGeneration;

	protected $model;

	protected $skipCriteria = true;

    protected $mailer;

	public function __construct(Order $model, Mailer $mailer)
    {
        $this->model = $model;
        $this->mailer = $mailer;
        parent::__construct();
    }

    public function model()
    {
        return 'App\Models\Admin\Order';
    }

    public function update(array $data, $id, $attribute='id')
    {
        $order = new Order();
        if(isset($data['status']) && $data['status'] == $order->getStatusCodeByName('ReadyToShip')){
            $order = $this->model->find($id);
            // check if any order items are not verified
            $errorFlag = false;
            foreach ($order->items as $item) {
                //if($item->status == 'Picked' || $item->status == 'Picking' || $item->status == 'Out of Stock'){
                if($item->status == 'Picked' || $item->status == 'Picking'){
                    $errorFlag = true;
                }
            }
            if($errorFlag){
                $errors =  response()->json(
                array(
                    'code' =>  422,
                    //'error' => 'Error updating status, please make sure all of the order items are proccessed or marked as cancelled (if out of stock).'
                    'error' => 'Error updating status, please make sure all of the order items are proccessed.'
                ));
                throw new HttpResponseException($errors);
            }
            // check that the order's consignment number is set
            if(is_null($order->consignment_no) || $order->consignment_no == ''){
                $errors =  response()->json(
                array(
                    'code' =>  422,
                    'error' => 'Error updating status, order\'s consignment number has not been set yet.'
                ));
                throw new HttpResponseException($errors);
            }
        }

        if (isset($data['status']) && $data['status'] == $order->getStatusCodeByName('Shipped')) {
            $data['shipped_date'] = date('Y-m-d H:i:s');
        }

        if (isset($data['paid_status']) && $data['paid_status'] == 1 && !isset($data['paid_date'])) {
            $data['paid_date'] = date('Y-m-d H:i:s');
        }

        $model = parent::update($data, $id, $attribute);

        if (isset($data['status']) && $data['status'] == $order->getStatusCodeByName('Shipped')) {
            $order_proc = new OrderProc();
            $order_proc->decrementReservedQuantity($id);
        }

        return $model;
    }

    public function getOrderItems($order_id)
    {
        $order = $this->model->find($order_id);
        $itemDetails = array();

        foreach ($order->items as $item) {
            $product = $item->ref->product;
            if(!is_null($product)){
                $itemDetails[] = ['item' => $item->toArray(), 'product' => $product->toArray()];
            }
        }

        //$order->product = $product;
    	return json_decode(json_encode($itemDetails), FALSE);
    }

    public function getOrderNotes($data)
    {
        $order = $this->model->find($data['orderId']);
        $orderNotes = array();
        foreach($order->orderNotes as $orderNote){
            // flag to indicate whether or not to keep searching for child notes
            $search = true;
            $orderNote->childrenNotes = array();
            $orderNote->lastID = $orderNote->id;
            $immediateParentId = $orderNote->id;
            $notesTemp = array();
            while($search) {
                $child = OrderNote::where("previous_note_id", '=', $immediateParentId)->first();

                // if child note exists
                if (!is_null($child)) {
                    $immediateParentId = $child->id;
                    $author = User::where('id', '=', $child->user_id)->value('first_name');
                    $notesTemp[] = array("id"=>$immediateParentId, "notes"=>$child->notes, "first_name"=>$author, "created_at"=>$child->created_at);
                    $orderNote->lastID = $child->id;
                }
                else {
                    $orderNote->childrenNotes = $notesTemp;
                    $search = false;
                }
            }
        }

        return $order->orderNotes;
    }

    public function getHistory($data){
        $order = $this->model->find($data['orderId']);
        $historyDetails = array();
        foreach ($order->orderHistory as $record) {
            $record->user_name = 'System';
            if($record->user_id > 0){
                $username = User::withTrashed()->where('id', '=', $record->user_id)->value('first_name');
                $record->user_name = $username;
            }
            // to cater for 0000-00-00 00:00:00 (which shouldn't be happening)
            if($record->getOriginal('created_at') != '0000-00-00 00:00:00'){
                $record->invalidDate = false;
            }
            else{
                $record->invalidDate = true;
            }

            $historyDetails[] = $record;
        }

        //$order->product = $product;
        return $historyDetails;
    }

    /**
     *  $qty : quantity to return / cancel, default to 1
     */
    public function processCancelReturn($data, $isCancel, $qty = 1, $syncToShopify = true, $restock = true, $strict = true) {

        $orderItem = OrderItem::with('order')->findOrFail($data['item_id']);
        $statusCode = array_flip(Order::$statusCode);

        // validation, $strict is a flag for when order comes in by webhook and could not follow our flow
        if ($strict) {
        	if($isCancel && !$this->cancellable($orderItem->order)) {
	            return 'Item cannot be cancelled due to order status.';
	        }
	        else if (!$isCancel && !$this->fulfilled($orderItem->order)) {
	            return 'Unfulfilled item cannot be returned.';
	        }
	        else if (!$isCancel && $orderItem->status == 'Out of Stock') {
	            return 'Out of stock item cannot be returned.';
	        }
        }

        $log = ReturnLog::where('order_id', $orderItem->order->id)
                ->where('order_item_id', $orderItem->id)
                ->first();

        if ($log) {
            return 'Item has already been cancelled/returned';
        }

        if($qty > $orderItem->quantity) {
            return 'Quantity to '. ($isCancel ? 'cancel' : 'return') .' is greater than item quantity.';
        }

        if (isset($data['remark']) && $data['remark'] == 'Wrongly sent by FMHW') {
            $orderItem->merchant_shipping_fee = 0.00;
            $thirdPartyReport = ThirdPartyReport::where('order_item_id', '=', $orderItem->id)->first();
            if(!is_null($thirdPartyReport)){
                ThirdPartyReport::where('order_item_id', '=', $orderItem->id)->update(['channel_shipping_fees' => 0.00]);
                $log = array(
                            'tp_report_id'  => $thirdPartyReport->id,
                            'old_value'     => $orderItem->merchant_shipping_fee,
                            'new_value'     => 0.00,
                            'field'         => 'third_party_report.channel_shipping_fees',
                            'modified_by'   => $data['user_id'],
                        );
                ThirdPartyReportLog::create($log);
            }
        }

        // update item
        $prevStatus = $orderItem->status;
        $orderItem->quantity -= $qty;
        $eventInfo['fromStatus'] = $orderItem->status;
        $orderItem->status = $isCancel ? 'Cancelled' : 'Returned';
        $eventInfo['toStatus'] = $orderItem->status;
        $orderItem->save();

        // if item is updated from out of stock, check and update order's partially_fulfilled column
        if($prevStatus == 'Out of Stock'){
            $orderId = $orderItem->order->id;
            $oosOrderItems = OrderItem::where('status', '=', 'Out of Stock')->where('order_id', '=', $orderId);
            // \Log::info($oosOrderItems->count());
            if($oosOrderItems->count() == 0){
                // no order items are out of stock, set order's partially fulfilled column to 0
                $oosData = array(
                    'partially_fulfilled' => 0,
                );
                $oosOrder = $this->update($oosData, $orderId);
            }
        }

        Event::fire(new OrderUpdated($orderItem->order->id, 'Item Status Updated', 'order_items', $orderItem->id, $eventInfo, $data['user_id']));

        // update picking item if it exists
        $pickingItem = PickingItem::where('id', '=', $data['item_id'])->first();

        // insert return log
        $returnLog = new ReturnLog;
        $returnLog->remark = (isset($data['remark']) ? $data['remark'] : '');
        $returnLog->completed_at = $isCancel ? date('Y-m-d H:i:s') : null;
        $returnLog->status = $isCancel ? 'Restocked' : 'In Transit';
        $returnLog->member_id = $orderItem->order->member_id;
        $returnLog->user_id = $data['user_id'];
        $returnLog->order_id = $orderItem->order_id;
        $returnLog->order_item_id = $orderItem->id;
        $returnLog->quantity = $qty;
        $returnLog->order_status = $orderItem->order->getStatusName();
        $returnLog->amount = $orderItem->sold_price;
        $returnLog->ref_id = !empty($data['ref_id']) ? $data['ref_id'] : NULL;
        $returnLog->refund_type = $orderItem->order->payment_type;

        $returnLog->save();
        Activity::log('Item ' . $orderItem->id . ' has been ' . $orderItem->status, $data['user_id']);

        $eventType = ($isCancel && $restock) ? 'Returned Item: Restocked' : 'Returned Item: In Transit';
        $eventInfo = array('orderItemRefId' => $orderItem->ref_id);

        // fire order updated event to record order history
        Event::fire(new OrderUpdated($orderItem->order_id, $eventType, 'return_log', $returnLog->id, $eventInfo, $data['user_id']));

        // store credit
        if (!empty($data['store_credit']) && $data['store_credit'] > 0 && $orderItem->order->member_id > 0) {
            $member = Member::find($orderItem->order->member_id)->increment('store_credits', $data['store_credit']);
            \StoreCreditsLog::create(array(
                'sale_id'       => $orderItem->order_id,
                'amount'        => $data['store_credit'],
                'member_id'     => $orderItem->order->member_id,
                'user_id'       => $data['user_id'],
                'sale_item_id'  => $orderItem->id
            ));
            Activity::log('Store credit ' . $data['store_credit'] . ' has been credited to member ' . $orderItem->order->member_id, $data['user_id']);
        }

        // restock - adding back quantity
        if ($isCancel && $restock) {
            $order_proc = new OrderProc();
            $order_proc->processReturnforReservedQuantity($orderItem->order_id, $orderItem->id, $returnLog->quantity);
            Activity::log('Reserved Quantity for channel_sku_id ('. $orderItem->ref_id .') has been decremented by '. $returnLog->quantity, $data['user_id']);

            $channel_sku = ChannelSKU::where('channel_sku_id', $orderItem->ref_id)->first();
            // $oldQuantity = $channel_sku->channel_sku_quantity;
            // $channel_sku->increment('channel_sku_quantity', $returnLog->quantity);
            // $channel_sku->touch();

            // event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $oldQuantity, 'ReturnLog', $returnLog->id));
            event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $returnLog->quantity, 'ReturnLog', $returnLog->id, 'increment'));

            // if all items are cancelled, cancel the order
            $items = OrderItem::where('order_id', $returnLog->order_id)->where('ref_type', '=', 'ChannelSKU')->get();
            $cancel_order = true;
            foreach($items as $item) {
                if($item->status != 'Cancelled') {
                    $cancel_order = false;
                    break;
                }
            }

            if($cancel_order) {
                $orderItem->order->cancelled_status = 1;
                $orderItem->order->partially_fulfilled = 0;
                $orderItem->order->cancelled_date = date('Y-m-d H:i:s');
                $orderItem->order->save();
                Activity::log('Order ('. $returnLog->order_id .') has been cancelled.', $data['user_id']);

                // fire order updated event to record order history
                Event::fire(new OrderUpdated($orderItem->order_id, 'Order Cancelled', 'orders', $orderItem->order_id, array(), $data['user_id']));
            }

            // sync to shopify
            if ($syncToShopify) {
            	$channel = Channel::findOrFail($orderItem->order->channel_id);
	            if ($channel->channel_type_id == 6) {
	                $input['channel_id'] = $orderItem->order->channel_id;
	                $input['item_id'] = $orderItem->id;
	                $input['restock'] = true;

	                $syncRepo = new SyncRepository;
	                $newSync = $syncRepo->createItemRefund($input);
	            }
            }
        }

        $credit_note = $this->generateCreditNote($orderItem->order_id);

        return $returnLog;
    }

    function createManualOrder($request)
    {
        //return $request;
        $orders = array();
        $item_HW_discount = $request->total_discount;

        $order = new Order;


        //convert to UTC because in Order model will be converted to user's timezone
        $adminTz = User::where('id', '=', $request->user_id)->value('timezone');
        $tpOrderDate =  Helper::convertTimeToUTC($request->order_date.' '.$request->order_time, $adminTz);

        $channel = Channel::where('id', '=', $request->channel)->with('channel_detail')->first();
        /*//check the shipping fee
        $checked = false;
        $resultState = '';
        $resultCountry = '';
        $resultLocality = '';
        $googleapisUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=';
        $wholeMalaysia = array_merge(config('globals.west_malaysia_state'), config('globals.east_malaysia_state'));
        foreach ($wholeMalaysia as $state) {
            if ($state == $request->recipient_adddres_state) {
                $resultState = $request->recipient_adddres_state;
                $checked = true;
            }
        }
        if ($checked == false) {
            //https://maps.googleapis.com/maps/api/geocode/json?address=94300+malaysia
            $googleapisResponce = '';
            $country = preg_replace('/\s+/', '%20', $request->recipient_address_country);
            $googleapisResponce = json_decode(file_get_contents($googleapisUrl.$request->recipient_address_postcode.'+'.$country), true);
            //dd($googleapisResponce['results'][0]['address_components']);
            if ($googleapisResponce['status'] == 'OK') {
                foreach ($googleapisResponce['results'][0]['address_components'] as $result) {
                    if ($result['types'][0] == 'administrative_area_level_1') {
                        $resultState = $result['long_name'];
                    }
                    if ($result['types'][0] == 'country') {
                        $resultCountry = $result['long_name'];
                    }
                    if ($result['types'][0] == 'locality') {
                        $resultLocality = $result['long_name'];
                    }
                }
                $checked = true;
            }
        }

        if ($checked) {
                $checked = false;
                $getRate = is_null($channel->channel_detail->shipping_rate)? null: $channel->channel_detail->shipping_rate;
                $shippingRate = is_null(json_decode($getRate, true))? array(): json_decode($getRate, true);

                foreach ($shippingRate as $rate) {
                    switch($rate['location']){
                        case 'All':
                            $baseAmount = $rate['base_amount'];
                            $baseGrams = $rate['base_grams'];
                            $incrementAmount = $rate['increment_amount'];
                            $incrementGrams = $rate['increment_grams'];
                            $checked = true;
                            break;
                        case 'West Malaysia':
                            $locations = config('globals.west_malaysia_state');
                            foreach ($locations as $location) {
                                if ($location == $resultState) {
                                    $baseAmount = $rate['base_amount'];
                                    $baseGrams = $rate['base_grams'];
                                    $incrementAmount = $rate['increment_amount'];
                                    $incrementGrams = $rate['increment_grams'];
                                    $checked = true;
                                    break;
                                }
                            }
                            break;
                        case 'East Malaysia':
                            $locations = config('globals.east_malaysia_state');
                            foreach ($locations as $location) {
                                if ($location == $resultState) {
                                    $baseAmount = $rate['base_amount'];
                                    $baseGrams = $rate['base_grams'];
                                    $incrementAmount = $rate['increment_amount'];
                                    $incrementGrams = $rate['increment_grams'];
                                    $checked = true;
                                    break;
                                }
                            }
                            break;
                        default:
                            if ($rate['location'] != 'Other') {
                                 //user input checking
                                $checkingUserNewInput = json_decode(file_get_contents($googleapisUrl.$rate['location']), true);
                                $newResultState = '';
                                $newResultCountry = '';
                                if ($checkingUserNewInput['status'] == 'OK') {
                                    foreach ($checkingUserNewInput['results'][0]['address_components'] as $newResult) {
                                        if ($newResult['long_name'] == $resultState || $newResult['long_name'] == $resultCountry || $newResult['long_name'] == $resultLocality) {
                                            $baseAmount = $rate['base_amount'];
                                            $baseGrams = $rate['base_grams'];
                                            $incrementAmount = $rate['increment_amount'];
                                            $incrementGrams = $rate['increment_grams'];
                                            $checked = true;
                                        }
                                    }
                                }
                            }elseif ($rate['location'] == 'Other') {
                                 if ($checked == false) {
                                    $baseAmount = $rate['base_amount'];
                                    $baseGrams = $rate['base_grams'];
                                    $incrementAmount = $rate['increment_amount'];
                                    $incrementGrams = $rate['increment_grams'];
                                }
                            }

                            break;
                    }
                }
            }
        */
        // cumulative fields
        $order->merchant_id_bk = 0;
        $order->channel_id = $request->channel;
        $order->subtotal = $request->subtotal;
        $order->total = $request->amount_paid;
        $order->shipping_fee = ($request->shipping_fee != '' ? $request->shipping_fee : '0.00');
        $order->total_discount = $request->total_discount + $item_HW_discount; //$item_HW_discount + $item_elevSt_discount;
        $order->cart_discount = ($request->total_discount != '' ? $request->total_discount : '0.00');
        $order->tp_order_id = $request->tp_code;
        $order->tp_order_code = $request->tp_code;
        $order->tp_order_date = $tpOrderDate;
        $order->tp_source = 'manual';
        $order->status = Order::$newStatus;
        $order->paid_status = true;
        $order->paid_date = $tpOrderDate;
        $order->cancelled_status = false;
        $order->payment_type = $request->payment_type;
        $order->shipping_recipient = $request->recipient_name;
        $order->shipping_phone = $request->recipient_contact;
        $order->shipping_street_1 = $request->recipient_address_1;
        $order->shipping_street_2 = $request->recipient_address_2;
        $order->shipping_postcode = $request->recipient_address_postcode;
        $order->shipping_city = $request->recipient_address_city;
        $order->shipping_state = $request->recipient_address_state;
        $order->shipping_country = $request->recipient_address_country;
        $order->shipping_provider = $request->shipping_provider;
        $order->consignment_no = null;
        $order->currency = $request->currency;
        $order->forex_rate = '1.00';
        $tp_extra = array(
            'created_at' => $request->order_date.' '.$request->order_time,
            'sale_status' => Order::$newStatus,
            'amount_paid' => $request->amount_paid,
            'confirm_date' => Carbon::now()
        );

        if(!empty($request->shipping_no))
            $tp_extra['shipping_no'] = $request->shipping_no;

        if(!empty($request->promotion_code))
            $tp_extra['discount_codes'] = $request->promotion_code;

        $order->tp_extra = json_encode($tp_extra);

        // $order->merchant_shipping_fee = $merchant_shipping_fee;

        // create new member object
        $member = new Member;
        $member->member_name = $request->customer_name;
        $member->member_type = 1;
        $member->member_email = $request->customer_email;
        $member->member_mobile = $request->customer_contact;

        $orders['success'] = true;
        $orders['order'] = $order;
        $orders['member'] = $member;
        $orders['user_id'] = $request->user_id;

        // create items object
        for ($i=0; $i< count($request->hubwire_sku); $i++) {
            $item = new OrderItem;
            $item->ref_type = 'ChannelSKU';
            $item->sold_price = $request->sold_price[$i];
            $item->tax_inclusive = 1;
            $item->tax_rate = 0.06;
            $item->tax = round(($request->sold_price[$i] / 1.06 * 0.06), 2);
            $item->quantity = $item->original_quantity = 1;
            $item->discount = round(($request->unit_price[$i] - $request->sale_price[$i]), 2);
            $item->tp_discount = ($request->discount[$i] > 0 ? $request->discount[$i] : 0);
            $item->weighted_cart_discount = ($request->weighted_discount[$i] > 0 ? $request->weighted_discount[$i] : 0);
            $item->tp_item_id = (!empty($request->tp_item_id[$i]) ? $request->tp_item_id[$i] : null);
            $item->channel_sku_id = $request->channel_sku[$i];
            $item->product_name = $request->product_name[$i];

            $orders['items'][] = $item;
        }
        return $orders;
    }

    public function createOrder($channel_id, $data)
    {
        $response = Order::createOrder($channel_id, $data);
        return $response;
    }

    public function fulfilled($order){
        return $order->status >= Order::$shippedStatus ? true : false;
    }

    public function cancellable($order){
        return $order->status >= Order::$newStatus && $order->status < Order::$shippedStatus ? true : false;
    }

    public function shippable($order){
        return $order->status >= Order::$readyToShipStatus ? true : false;
    }

    public function search($request)
    {
        // \Log::info(print_r($request->all(), true));

        $response = array();
        $searchParams = array();
        $params = array();
        $mustparams = array();
        $searchParams['index'] = env('ELASTICSEARCH_ORDERS_INDEX','orders');
        $searchParams['type'] = 'sales';
        $searchParams['from'] = $request->get('start',0);
        $searchParams['size'] = $request->get('length',50);

        $searchVals = $request->get('columns');
        $toSkip = array();
        $level = $request->get('filterByLevel',null);
        if(!empty($level))
        {
            $toSkip = ['status','cancelled_status','tp_order_date'];
            $mustparams[] = ['range'=>['status' => ['gte'=>Order::$newStatus,'lt'=>Order::$shippedStatus]]];
            $mustparams[] = ['match'=>['cancelled_status' => 0]];
            $now = Carbon::now('UTC');
            switch(intval($level))
            {
                case 1:
                    $mustparams[] = ['range'=>['tp_order_date' => ['gt'=>'now-24h','lt'=>date('Y-m-d 00:00:00')]]];
                break;
                case 2:
                    $mustparams[] = ['range'=>['tp_order_date' => ['gt'=>'now-48h','lte'=>'now-24h']]];
                break;
                case 3:
                    $mustparams[] = ['range'=>['tp_order_date' => ['gt'=>'now-72h','lte'=>'now-48h']]];
                break;
                case 4:
                    $mustparams[] = ['range'=>['tp_order_date' => ['lte'=>'now-72h']]];
                break;
                default:
            }
        }

        if(!empty($searchVals))
        {
            foreach($searchVals as $searchVal)
            {
                if(""!==trim($searchVal['search']['value']))
                {
                    if(in_array($searchVal['name'], $toSkip)) continue;
                    if($searchVal['name']==='search_order')
                    {
                        $params[] = ['match_phrase'=>['items.ref.product.name'=>$searchVal['search']['value']]];
                        $params[] = ['match_phrase'=>['items.ref.product.brand_name'=>$searchVal['search']['value']]];
                        $params[] = ['match_phrase'=>['items.merchant.name'=>$searchVal['search']['value']]];
                        $params[] = ['match'=>['items.ref.product.id'=>intval($searchVal['search']['value'])]];
                        $params[] = ['match'=>['items.ref.sku.hubwire_sku'=>$searchVal['search']['value']]];
                        $params[] = ['match_phrase'=>['member.member_name'=>$searchVal['search']['value']]];
                    }
                    else if($searchVal['name']==='created_at')
                    {
                        $val = explode(" - ", $searchVal['search']['value']);
                        $dates[0] = date('Y-m-d H:i:s', strtotime($val[0]));
                        $dates[1] = date('Y-m-d H:i:s', strtotime($val[1]));
                        $mustparams[] = ['range' => ['created_at' => ['gte'=>$dates[0],'lte'=>$dates[1]]]];
                    }
                    else if ($searchVal['name']=='tp_order_code')
                    {
                        $order_code = array();
                        $order_code['bool']['should'][] = ['match_phrase'=>['tp_order_code'=>$searchVal['search']['value']]];
                        if(intval($searchVal['search']['value'])>0)
                        $order_code['bool']['should'][] = ['match'=>['tp_order_id'=>$searchVal['search']['value']]];
                        $mustparams[] = $order_code;
                    }
                    else if($searchVal['name']=='status')
                    {
                        $statuses = explode('|', $searchVal['search']['value']);
                        $mustparams[] = ['terms'=>['status'=>$statuses]];
                    }
                    else if($searchVal['name']=='channel_name')
                    {
                        $mustparams[] = ['match'=>['channel.name'=>$searchVal['search']['value']]];
                    }
                    else
                    {
                        $numbers = ["id","cancelled_status","partially_fulfilled","merchant_id","channel_id","paid_status"];
                        if(in_array($searchVal['name'],$numbers))
                        $mustparams[] = ['match'=>[$searchVal['name']=>intval($searchVal['search']['value'])]];
                        else
                        $mustparams[] = ['match_phrase'=>[$searchVal['name']=>$searchVal['search']['value']]];
                    }
                }
            }
        }

        if(count($params) > 0)
        {
            $searchParams['body']['query']['bool']['should'] = $params;
            $searchParams['body']['query']['bool']['minimum_should_match'] =1;
        }

        if(count($mustparams) > 0){
            $searchParams['body']['query']['bool']['must'] = $mustparams;
        }

        // \Log::info(json_encode($searchParams));
        $searchParams['body']['sort'] = array('updated_at' => array('order' => 'desc'));

        $search_es = json_decode(json_encode(\Es::search($searchParams)));
        // \Log::info(print_r($search_es, true));
        $data = array();
        if($search_es->hits->total > 0)
        {
            foreach($search_es->hits->hits as $record)
            {
                $data[] = $record->_source;
            }
        }

        // Get total count of the documents
        $stats = json_decode(json_encode(\Es::indices()->stats(
                [
                    'index'=>$searchParams['index'],
                    'metric'=>['docs']
                ])));
        $response['recordsTotal'] = $stats->_all->total->docs->count;

        $response['success'] = true;
        $response['data'] = $data;
        $response['recordsFiltered'] = $search_es->hits->total;

        //dd($response);

        return $response;

    }

    public function searchDB($request, $merchantId = null) {
        //$orders = Order::leftjoin(\DB::raw("(SELECT order_id, quantity, original_quantity FROM order_items) AS o_items"), 'orders.id', '=', 'o_items.order_id');

        $orders = Order::leftjoin('order_items', 'orders.id', '=', 'order_items.order_id')
                            /*->leftjoin('channel_sku',function($query)
                            {
                                $query->on('channel_sku.channel_sku_id', '=', 'order_items.ref_id') ->where('order_items.ref_type', '=', 'ChannelSKU');
                            })*/
                            //->leftjoin('products', 'products.id', '=', 'channel_sku.product_id')
                            ->leftJoin('members', 'members.id', '=', 'orders.member_id');
                            //->leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id');

        // separate query for counting number of search results
        $ordersCount = Order::leftjoin('order_items', 'orders.id', '=', 'order_items.order_id');

        $searchVals = $request->get('columns',null);

        $toSkip = ['search_order'];
        $level = $request->get('filterByLevel','');
        if(!empty($level)){

            $toSkip = ['status','cancelled_status','tp_order_date'];
            $orders = $orders->level($level);
            $ordersCount = $ordersCount->level($level);

        }

        if(!empty($searchVals))
        {
            // search parameters
            foreach($searchVals as $searchVal) {
                if ($searchVal['search']['value']!='') {
                    if ($searchVal['name']=='created_at') {
                        $val = explode(" - ", $searchVal['search']['value']);
                        $dates[0] = Carbon::createFromFormat('m/d/Y H:i:s', $val[0]."00:00:00",'Asia/Kuala_Lumpur')->setTimezone('UTC')->format('Y-m-d H:i:s');
                        $dates[1] = Carbon::createFromFormat('m/d/Y H:i:s', $val[1]."23:59:59",'Asia/Kuala_Lumpur')->setTimezone('UTC')->format('Y-m-d H:i:s');
                        $orders = $orders->whereBetween('orders.'.$searchVal['name'], $dates);
                        $ordersCount = $ordersCount->whereBetween('orders.'.$searchVal['name'], $dates);
                    }
                    else if ($searchVal['name']=='tp_order_code') {
                        $orders = $orders->where(function ($query) use ($searchVal) {
                            $query->where('orders.'.$searchVal['name'], 'LIKE', '%'.$searchVal['search']['value'].'%')
                                  ->orWhere('orders.tp_order_id', 'LIKE', '%'.$searchVal['search']['value'].'%');
                        });
                        $ordersCount = $ordersCount->where(function ($query) use ($searchVal) {
                            $query->where('orders.'.$searchVal['name'], 'LIKE', '%'.$searchVal['search']['value'].'%')
                                  ->orWhere('orders.tp_order_id', 'LIKE', '%'.$searchVal['search']['value'].'%');
                        });
                    }
                    else if ($searchVal['name']=='merchant_id') {
                        $ordersCount = $ordersCount->where('order_items.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        $orders = $orders->where('order_items.'.$searchVal['name'], '=', $searchVal['search']['value']);
                    }
                    else if ($searchVal['name']=='status') {
                        if($searchVal['search']['regex'] == true){
                            $statuses = explode('|', $searchVal['search']['value']);
                            $ordersCount = $ordersCount->whereIn('orders.'.$searchVal['name'], $statuses);
                            $orders = $orders->whereIn('orders.'.$searchVal['name'], $statuses);
                        }else{
                            $ordersCount = $ordersCount->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                            $orders = $orders->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        }
                    }else if ($searchVal['name']=='cancelled_status') {
                        $ordersCount = $ordersCount->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        $orders = $orders->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        $cancelled = $searchVal['search']['value'];
                    }
                    else if ($searchVal['name']=='since_id') {
                        $ordersCount = $ordersCount->where('orders.id', '>', $searchVal['search']['value']);
                        $orders = $orders->where('orders.id', '>', $searchVal['search']['value']);
                    }
                    else {
                        if(in_array($searchVal['name'], $toSkip)) continue;
                        $ordersCount = $ordersCount->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                        $orders = $orders->where('orders.'.$searchVal['name'], '=', $searchVal['search']['value']);
                    }
                }
            }
        }
        /*
        $promoTable = $orders->leftjoin('order_items', 'orders.id', '=', 'order_items.sale_id')
                ->select('order_items.sale_id, order_items.product_type as promo')
                ->where('product_type','PromotionCode')
                ->groupBy('orders.id');
        //$orders = $orders->union($promoTable)->groupBy('orders.id');->get();
        */

        // column sorting
        if ($request->input('order'))
        {
            $colNum = $request->input('order')[0]['column'];
            $direction = null;
            if ($request->input('order')[0]['dir'] == 'desc')
            {
                $direction = 'desc';
            }else
            {
                $direction = 'asc';
            }
            $colName = $request->input("columns")[$colNum]['name'];
            $orders = $orders->orderBy($colName, $direction);
        }
        else{
            $orders = $orders->orderBy('orders.created_at', 'desc');
        }

        $orders = $orders->select('orders.id', 'orders.created_at', 'total', 'order_items.merchant_id', 'orders.status', 'payment_type', 'paid_status', 'tp_order_code', 'orders.currency', 'orders.channel_id', 'member_name', 'cancelled_status', \DB::Raw('sum(quantity) as item_quantity, sum(original_quantity) as original_quantity'))->groupBy('orders.id');
        $this->applyCriteria();

        $ordersCount = $ordersCount->select('orders.id')->groupBy('orders.id')->get();
        if ($request->input('length')==-1) {
            $orders = $orders->get();
        }
        else {
            $orders = $orders->skip(request()->input('start', 0))
                            ->take(request()->input('length', 10))
                            ->get();
        }
        //checking for the total quantity and total amount for a merchant
        foreach ($orders as $order) {
           if (!empty($merchantId)) {
                $orderItems = OrderItem::where('order_id', '=', $order['id'])->where('merchant_id', '=', $merchantId)->get();
                $orderItemAmount = 0;
                $orderItemQuantity = 0;
                $orderItemReturned = 0;
                $orderItemCancelled = 0;
                foreach ($orderItems as $orderItem) {
                    $orderItemQuantity += $orderItem['original_quantity'];
                    if ($orderItem['status'] == 'Returned') {
                        $orderItemReturned += $orderItem['original_quantity'];
                    }elseif ($orderItem['status'] == 'Cancelled') {
                       $orderItemCancelled += $orderItem['original_quantity'];
                    }
                    $orderItemAmount += ($orderItem['tax_inclusive'])? $orderItem['sold_price'] : ($orderItem['sold_price']+$orderItem['tax']);
                }
                $order['total_item_quantity'] = ($orderItemQuantity > 0)? "$orderItemQuantity" : null;
                $order['total_item_amount'] = "$orderItemAmount";
                $order['total_item_returned'] = ($orderItemReturned > 0)? "$orderItemReturned" : null;
                $order['total_item_cancelled'] = ($orderItemCancelled > 0)? "$orderItemCancelled" : null;
            }
        }

        // get total number of orders
        $count = \DB::select(\DB::raw("select count(*) as total from orders"));

        $response['success'] = true;
        $response['data'] = $orders;
        $response['recordsFiltered'] = !empty($ordersCount)?count($ordersCount):'0';
        $response['recordsTotal'] = isset($count[0]->total)?$count[0]->total:'0';
        //dd($response);

        return $response;
    }

    // Get number of order based on levels
    public function countLevels()
    {
        for($i=1;$i<=4;$i++){
            $levels[$i] = number_format(Order::level($i)->count());
        }
        return $levels;
    }
    // Get number of orders for each status
    public function countOrders($request) {
        $statusToCount = array(
            Order::$newStatus => 'new',
            Order::$pickingStatus => 'picking',
            Order::$packingStatus => 'packing',
            Order::$readyToShipStatus => 'ready_to_ship'
        );

        $merchantId = $request->input('merchant_id');
        $channelId = $request->input('channel_id');
        $subquery = (!empty($merchantId))?"WHERE merchant_id = $merchantId":'';

        // if filtering by merchant ID, need to search order_items table
        $query = \DB::table('orders')
                    ->leftjoin(\DB::raw("(SELECT merchant_id,
                                    order_id
                                    FROM order_items
                                    $subquery
                                    group by order_id)
                                    AS order_items"), 'orders.id', '=', 'order_items.order_id')
                    ->where('cancelled_status', '=', 0);

        if (!empty($channelId))
            $query = $query->where('orders.channel_id', $channelId);

        $query = $query->whereNotNull('order_id');

        $selectString = '';
        foreach($statusToCount as $key=>$val) {
            $selectString .= "count(case orders.status when $key then 1 else null end) as $val, ";
        }

        $selectString .= "count(case when
                                    orders.partially_fulfilled = 1
                                    and orders.cancelled_status = 0
                                    and orders.status >= ".Order::$newStatus.
                                    " and orders.status < ".Order::$shippedStatus.
                                " then 1
                                else null
                                end) as partially_fulfilled";
        $query = $query->select(\DB::raw($selectString))->get();//toSql();

        return $query;
    }

    public function createNote($request, $id, $userId){
        $note = new OrderNote;
        $note->user_id = $userId;
        $note->order_id = $id;
        $note->notes = $request->get('notes');
        $note->note_type = ucfirst($request->get('note_type'));

        if ($note->note_type=="Done") {
            // set parent note to done
            $pNote = OrderNote::where('id', '=', $request->get('parent_note_id'))->first();
            $pNote->note_status = "Done";
            $pNote->save();
        }

        if ($note->note_type=="Done" || $note->note_type=="Comment") {
            // set previous note ID
            $note->previous_note_id = $request->get('note_id');
        }
        $note->save();

        // send email
        if ($note->note_type == "Attention Required") {
            $order = $this->model->find($id);
            $channelName = Channel::where('id', '=', $order->channel_id)->value('name');
            $email_data = array();
            $userName = User::where('id', '=', $userId)->value('first_name');
            $email_data['title'] = '('.$channelName.') Order note created at Order ID : '.$id;
            $email_data['user_name'] = $userName;
            $email_data['note_content'] = $note->notes;
            $email_data['order_id'] = $id;

            $sent = $this->mailer->attentionRequiredNote($email_data);

            Activity::log('Attention required order note was created for order ID ' . $id, $userId);
        }else if($note->note_type == 'Comment'){
            Activity::log('User added a comment to a order note in order ID ' . $id, $userId);
        }else if($note->note_type=="Done"){
            Activity::log('Attention required order note completed for order ID ' . $id, $userId);
        }else{
            Activity::log('General order note was created for order ID ' . $id, $userId);
        }

        return $note;
    }

    public function packItem($request, $id, $userId){
        $hw_sku = $request->get('hw_sku');
        $orderItem = OrderItem::select(
                                    'sku.hubwire_sku',
                                    'orders.id',
                                    'order_items.id',
                                    'order_items.order_id',
                                    'orders.channel_id',
                                    'channel_sku.channel_id',
                                    'channel_sku.sku_id',
                                    'sku.sku_id',
                                    'sku.product_id',
                                    'order_items.ref_id',
                                    'order_items.status',
                                    'order_items.id',
                                    'order_items.ref_type',
                                    'products.name'
                                )
                            ->join('orders', 'order_items.order_id', '=', 'orders.id')
                            ->join('channel_sku', function($join){
                                $join->on('orders.channel_id', '=', 'channel_sku.channel_id');
                                $join->on('channel_sku.channel_sku_id', '=', 'order_items.ref_id');
                            })
                            ->join('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                            ->join('products', 'sku.product_id', '=', 'products.id')
                            ->where('order_items.status', '=', 'Picked')
                            ->where('sku.hubwire_sku', '=', $hw_sku)
                            ->where('order_items.order_id', '=', $id)
                            ->where('order_items.ref_type', '=', 'ChannelSKU');
        if($orderItem->count() < 1){
            $response['error'][] = 'Unable to update ['.$hw_sku.'] as it was not picked yet, marked out of stock, cancelled/returned or the Hubwire SKU is not found within the order.';
        }
        else{
            $orderItem = $orderItem->first();
            $eventInfo['fromStatus'] = $orderItem->status;
            $orderItem->status = 'Verified';
            $eventInfo['toStatus'] = $orderItem->status;
            $orderItem->save();

            Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, $eventInfo, $userId));

            $response['order_item_id'] = $orderItem->id;
            $response['status'] = $orderItem->status;
        }
        return $response;
    }

    public function updateItemStatus($request, $id, $userId){
        $orderItemId = $request->input('orderItemId');
        // default value to picked if no 'status' input is submitted
        $status = $request->input('status', 'Picked');
        $orderItem = OrderItem::with('order')->findOrFail($orderItemId);
        $checkPartialFulfillment = false;

        // update order's partially fulfilled if none of the order items are out of stock.
        if($status == 'Picked' && $orderItem->status == 'Out of Stock'){
            // check if order item is able to convert back to picked from out of stock
            if($this->shippable($orderItem->order)){
                $errors =  response()->json(
                    array(
                        'code' =>  422,
                        'error' => 'Unable to update order item to Picked due to the order\'s status. Please refresh the page and try again.'
                    )
                );
                throw new HttpResponseException($errors);
            }

            $checkPartialFulfillment = true;
        }

        // update order item status
        $oldStatus = $orderItem->status;
        $orderItem->status = $status;
        $orderItem->save();

        Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, array('fromStatus' => $oldStatus, 'toStatus' => $orderItem->status), $userId));

        // audit trail
        Activity::log('Item ' . $orderItemId . '\'s status has been updated from ' . $oldStatus . ' to ' . $orderItem->status, $userId);

        if($checkPartialFulfillment){
            $oosOrderItems = OrderItem::where('status', '=', 'Out of Stock')->where('order_id', '=', $id);
            // \Log::info($oosOrderItems->count());
            if($oosOrderItems->count() == 0){
                // no order items are out of stock, set order's partially fulfilled column to 0
                $data = array(
                    'partially_fulfilled' => 0,
                );
                $order = $this->update($data, $id);
            }
        }

        return $orderItem;
    }

    public function getPromotionCodes($orderId)
    {
        $promotions = array();
        $order = Order::find($orderId);
        foreach ($order->items as $item) {
            if ($item->ref_type == 'PromotionCode') {
                $promotions[] = $item->ref->promo_code;
            }

            $tp_extra = json_decode($order->tp_extra, true);
            if (isset($tp_extra['discount_codes']) && !empty($tp_extra['discount_codes'])) {
                $promotions[] = $tp_extra['discount_codes'];
            }
        }

        return (!empty($promotions) ? implode(', ', $promotions) : 'None');
    }

    public function getOrderSheetInfo($orderId)
    {
        $order = $this->find($orderId);
        $items = (object) $this->getOrderItems($orderId);
        $member = Member::find($order->member_id);
        $paidStatusList = array(false => 'Unpaid', true => 'Paid');
        $statuses = array_flip(Order::$statusCode);

        $order->status = isset($statuses[$order->status])? $statuses[$order->status] : '';
        $order->paid_status = $paidStatusList[$order->paid_status];

        $channel = Channel::where('id', '=', $order->channel_id)->with('channel_type')->first();

        $returnLog = ReturnLog::where('order_id', '=', $orderId)->get();
        if(!empty($returnLog)){
            foreach($items as $item){
                $restocked_count = 0;
                $in_transit_count = 0;
                $rejected_count = 0;
                foreach($returnLog as $return){
                    if($return->order_item_id == $item->item->id){
                        if($return->status == 'Restocked') $restocked_count++;
                        if($return->status == 'In Transit') $in_transit_count++;
                        if($return->status == 'Rejected') $rejected_count++;
                    }
                }

                $item->returns = ['Restocked' => $restocked_count, 'InTransit' => $in_transit_count, 'Rejected' => $rejected_count];

            }
        }
        return compact('order', 'items', 'member', 'channel');
    }

    public function getReturnSlipInfo($orderId)
    {
        $order = $this->find($orderId);
        $items = $this->getOrderItems($orderId);
        $channel = Channel::with('channel_detail')->find($order->channel_id);

        return compact('order', 'items', 'channel');
    }
}
