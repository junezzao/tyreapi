<?php
namespace App\Models\Admin;

use DB;
use App\Models\Admin\Client;
use App\Models\Admin\Channel;
use App\Models\Admin\FailedOrder;
use App\Models\Admin\OrderHistory;
use App\Models\Admin\OrderStatusLog;
use App\Models\Admin\ReturnLog;
use App\Models\BaseModel;
use App\Models\Admin\Member;
use App\Models\Admin\Webhook;
use App\Repositories\Eloquent\SyncRepository;
use Config;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Http\Controllers\ElevenStreetController;
use Illuminate\Database\Eloquent\Model;
use Event;
use App\Events\OrderUpdated;
use Log;
use App\Helpers\Helper as Utils;
use Activity;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;
use App\Events\ChannelSkuQuantityChange;
use App\Models\Admin\Product;
use App\Services\Mailer;
use App\Modules\Fulfillment\Repositories\Eloquent\ReturnRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Models\Admin\RejectLog;

class Order extends BaseModel
{
    public static $statusCode = array(
        'Unknown'       => 0, // When unable to determine which status ( SHOULD NEVER HAPPEN )
        'Failed'        => 11,
        'Pending'       => 12,
        'New'           => 21,
        'Paid'          => 21, // NOT IN USE - only applicable to old orders before admin 3.0
        'Picking'       => 22,
        'Packing'       => 23,
        'ReadyToShip'   => 24,
        'Shipped'       => 31,
        'Completed'     => 32,
    );
    public static $failedStatus      = 11;
    public static $pendingStatus     = 12;
    public static $newStatus         = 21;
    public static $pickingStatus     = 22;
    public static $packingStatus     = 23;
    public static $readyToShipStatus = 24;
    public static $shippedStatus     = 31;
    public static $completedStatus   = 32;

    protected $table = "orders";

    protected $guarded = ['id'];

    protected $fillable = [];

    protected $casts = [
        'paid_status' => 'boolean',
        'cancelled_status' => 'boolean',
    ];

    public $by_merchant = false;

    // protected $appends = ['item_quantity','original_quantity'];

    public function getDates()
    {
        return [];
    }

    public function scopeFulfilled($query)
    {
        return $query->where('status', '>=', static::$shippedStatus);
    }

    public function scopeNotFulfilled($query)
    {
        return $query->where('status', '<', static::$shippedStatus);
    }

    public function scopeCancellable($query)
    {
        return $query->where(function ($query) {
            $query->where('status', '>=', static::$newStatus)
            ->orWhere('status', '<=', static::$shippedStatus);
        });
    }

    public function scopeLevel($query, $level){
        $query = $query->whereRaw('orders.status BETWEEN ? AND ?',[static::$newStatus,static::$shippedStatus-1] )
                ->leftjoin('channel_details', 'channel_details.channel_id', '=', 'orders.channel_id')
                ->where('channel_details.picking_manifest', true)
                ->where('cancelled_status','=',0);
        $now = Carbon::now('UTC');
        switch($level){
            default:
            case 1:
                $query = $query->whereRaw('( tp_order_date > DATE_SUB(?, INTERVAL 24 HOUR) AND date(tp_order_date) <> ? )',[$now->format('Y-m-d H:i:s'),$now->format('Y-m-d')]);
            break;
            case 2:
                $query = $query->whereRaw('( tp_order_date BETWEEN DATE_SUB(?, INTERVAL 48 HOUR) AND DATE_SUB(?, INTERVAL 24 HOUR) )',[$now->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s')]);
            break;
            case 3:
                $query = $query->whereRaw('( tp_order_date BETWEEN DATE_SUB(?, INTERVAL 72 HOUR) AND DATE_SUB(?, INTERVAL 48 HOUR) )',[$now->format('Y-m-d H:i:s'),$now->format('Y-m-d H:i:s')]);
            break;
            case 4:
                $query = $query->whereRaw('( tp_order_date < DATE_SUB(?, INTERVAL 72 HOUR) )',[$now->format('Y-m-d H:i:s')]);
            break;
        }
        return $query;

    }

    public function scopeReturnable($query)
    {
        return $query->where('status', '>=', static::$shippedStatus);
    }

    /**
     *
     * Relationships
     *
     */
     public function returns ()
    {
        return $this->hasMany('App\Models\Admin\ReturnLog');
    }

    public function items ()
    {
        return $this->hasMany('App\Models\Admin\OrderItem', 'order_id', 'id')->with('ref');
    }

    public function chargeableItems () {
        return $this->items()->chargeable();
    }

    public function channel ()
    {
        return $this->belongsTo('App\Models\Admin\Channel', 'channel_id', 'id');
    }

    public function itemSKUs ()
    {
        return $this->hasMany('App\Models\Admin\OrderItem', 'order_id', 'id')->where('ref_type', 'ChannelSKU')->with('ref');
    }

    public function member()
    {
        return $this->belongsTo('App\Models\Admin\Member');
    }

    public function statusLog()
    {
        return $this->hasMany('App\Models\Admin\OrderStatusLog');
    }

    public function orderNotes()
    {
        return $this->hasMany('App\Models\Admin\OrderNote')
        ->select('order_notes.id', 'order_id', 'notes', 'first_name', 'order_notes.created_at', 'note_type', 'note_status', 'previous_note_id')
        ->leftJoin('users', 'users.id', '=', 'order_notes.user_id')
        ->where('previous_note_id','=',0)
        ->orderBy('order_notes.created_at', 'asc');
    }


    public function orderHistory()
    {
        return $this->hasMany('App\Models\Admin\OrderHistory')->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    public function orderHistories()
    {
        return $this->morphMany('App\Models\Admin\OrderHistory', 'ref');
    }

    public function quantityLogApp ()
    {
        return $this->morphMany('App\Models\Admin\QuantityLogApp', 'ref', 'ref_table', 'ref_table_id');
    }

    public function orderDate ($order_id)
    {
        $paidDate = $this->statusLog->where('order_id', $order_id)->whereIn('to_status',['Completed','completed'])->first();
        if($paidDate){
            return $paidDate->created_at;
        }
        return false;
    }

    public function getStatusDate ($order_id, $status)
    {
        $date = $this->statusLog->where('order_id', $order_id)->where('to_status', $status)->first();
        if($date)
            return $date->created_at;

        return false;
    }

    public function getStatusName()
    {
        $statuses = array_flip(self::$statusCode);
        if($statuses[$this->status] == 'Paid')
            return 'New';
        else
            return $statuses[$this->status];
    }

    public static function createOrder($channel_id, $data)
    {
        // DB::beginTransaction();

        $response = array();
        $channelTypes = config('globals.channel_type');
        $channel = Channel::with('channel_detail')->findOrFail($channel_id);
        $order = $data['order'];
        $items = $data['items'];
        $userId = (isset($data['user_id'])) ? $data['user_id'] : 0;

        // check if order existed
        if(!empty($data['order']->tp_order_code)) {
            $check = Order::where('tp_order_code', '=', $data['order']->tp_order_code)->where('channel_id', '=', $channel_id)->first();
            if(!empty($check)) {
        		DB::rollback();
                $error = array();
                $error['error_desc'] = 'Order code #'. $check->tp_order_code .' already existed. It will not be created again. | System order ID: ' . $check->id;
                $error['status_code'] = Utils::getStatusCode('VALIDATION_ERROR');
                return Utils::errorResponse($error, __METHOD__, __LINE__);
            }
        }
        else if(!empty($data['order']->tp_order_id)) {
            $check = Order::where('tp_order_id', '=', $data['order']->tp_order_id)->where('channel_id', '=', $channel_id)->first();
            if(!empty($check)) {
        		DB::rollback();
                $error = array();
                $error['error_desc'] = 'Order id '. $check->tp_order_id .' already existed. It will not be created again. | System order ID: ' . $check->id;
                $error['status_code'] = Utils::getStatusCode('VALIDATION_ERROR');
                return Utils::errorResponse($error, __METHOD__, __LINE__);
            }
        }

        // Validate SKUs first
        $sku_error = array();
        foreach($items as $item){
            if(isset($item->channel_sku_ref_id)) {
                $channel_sku = ChannelSKU::where('ref_id', $item->channel_sku_ref_id)
                                    ->where('channel_id', $channel_id)
                                    ->whereNull('deleted_at')
                                    ->whereHas('product', function($query) {
                                        $query->whereNull('deleted_at');
                                    })
                                    ->whereHas('sku', function($query) {
                                        $query->whereNull('deleted_at');
                                    })->first();
                if(is_null($channel_sku)){
                    $sku_error[] = $item->product_name .' ('. $channel->channel_type->name .' ORDER REF#: '. $order->tp_order_id .' CHANNEL SKU REF#:'. $item->channel_sku_ref_id .') is not valid SKU';
                }
            } elseif(isset($item->channel_sku_id)) {
                $channel_sku = ChannelSKU::where('channel_sku_id', $item->channel_sku_id)
                                    ->where('channel_id', $channel_id)
                                    ->whereNull('deleted_at')
                                    ->whereHas('product', function($query) {
                                        $query->whereNull('deleted_at');
                                    })
                                    ->whereHas('sku', function($query) {
                                        $query->whereNull('deleted_at');
                                    })->first();
                if(is_null($channel_sku)){
                    $sku_error[] = $item->product_name .' ('. $channel->channel_type->name .' ORDER REF#: '. $order->tp_order_id .' CHANNEL SKU#:'. $item->channel_sku_id .') is not valid SKU';
                }
            } else { // using sku_ref_id
                $sku = SKU::leftjoin('channel_sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                            ->where('sku.hubwire_sku', $item->sku_ref_id)
                            ->where('channel_sku.channel_id', $channel_id)
                            ->whereNull('channel_sku.deleted_at')
                            ->whereNull('sku.deleted_at')
                            ->whereHas('product', function($query) {
                                $query->whereNull('products.deleted_at');
                            })->first();
                if(is_null($sku)){
                    $sku_error[] = $item->product_name .' ('. $channel->channel_type->name .' ORDER REF#: '. $order->tp_order_id .' HUBWIRE SKU#:'. $item->sku_ref_id .') is not valid SKU';
                    continue;
                }
            }
        }

        // If order consists of product not found in Hubwire, insert into failed_orders table
        if(!empty($sku_error)){
            DB::rollback();

            $failed_order = FailedOrder::firstOrNew(array('channel_id' => $channel_id, 'tp_order_id' => $order->tp_order_id));
            $failed_order->channel_id = $channel_id;
            $failed_order->tp_order_id = $order->tp_order_id;
            $failed_order->tp_order_date = $order->tp_order_date;

            if($failed_order->exists && $failed_order->status <= 2){
                // if failed order has already existed, set status to pending instead
                try{
                    $foUserId = Authorizer::getResourceOwnerId();
                }catch(NoActiveAccessTokenException $e){
                    $foUserId = NULL;
                }

                $failed_order->status = 2;
                $failed_order->user_id = $foUserId;
                $failed_order->error = json_encode($sku_error);
            }else{
                $failed_order->status = 1;
                $failed_order->error = json_encode($sku_error);
            }

            $failed_order->save();

            $error = array();
            $error['error_desc'] = json_encode($sku_error);
            $error['status_code'] = Utils::getStatusCode('VALIDATION_ERROR');
            return Utils::errorResponse($error, __METHOD__, __LINE__);
        }

        // Create new order
        $order->channel_id = $channel_id;
        //$order->shipping_provider = ($channel_id == 57) ? 'GDex' : $order->shipping_provider;
        // $order->merchant_id = $channel->merchant_id;
        $order->total_tax = round(($order->total - $order->shipping_fee)/1.06*0.06, 2);
        //$order->created_at = $order->updated_at = date("Y-m-d H:i:s");
        $order->created_at = $order->updated_at = Carbon::now()->setTimezone('UTC');

        // Create new member
        if (!empty($data['member'])) {
            $member = new Member;
            $member->member_name = $data['member']->member_name;
            $member->member_type = $data['member']->member_type;
            $member->member_email = $data['member']->member_email;
            $member->member_mobile = $data['member']->member_mobile;
            $member->channel_id = $channel_id;
            $member->merchant_id = 0;
            $member->save();

            // Create new shipping_addresses
            $address = new Address;
            $address->address_first_name = $address->address_name = $order->shipping_recipient;
            $address->address_first_line = $order->shipping_street_1;
            $address->address_second_line = $order->shipping_street_2;
            $address->address_city = $order->shipping_city;
            $address->address_postal_code = $order->shipping_postcode;
            $address->address_country = $order->shipping_country;
            $address->address_phone = $order->shipping_phone;
            $member->addresses()->save($address);

            $order->member_id = $member->id;
        }
        else {
            $order->member_id = 0;
        }
        //get shipping rate detail

    	//dd($baseGrams);
        $order->save();

        Event::fire(new OrderUpdated($order->id, 'Order Created', 'orders', $order->id, array(), $userId));
        Event::fire(new OrderUpdated($order->id, 'Order Paid', 'orders', $order->id, array(), $userId));

        // Resolve failed_orders if exist and insert order id
        $checkFO = FailedOrder::where('tp_order_id', '=', $order->tp_order_id)->first();

        if($checkFO){
            $checkFO->status = 3;
            try{
                $foUserId = Authorizer::getResourceOwnerId();
            }catch(NoActiveAccessTokenException $e){
                $foUserId = NULL;
            }
            $checkFO->user_id = $foUserId;
            $checkFO->order_id = $order->id;
            $checkFO->save();

            if(is_null($foUserId)){
                $foUserId = 0;
            }

            Activity::log('Failed order ('. $checkFO->failed_order_id .') has been resolved.', $foUserId);
        }

        // Create new order item
        $itemMerchant = array();
        foreach($items as $item) {
            if(isset($item->channel_sku_ref_id)) {
                $channel_sku = ChannelSKU::where('ref_id', '=', $item->channel_sku_ref_id)->where('channel_id', '=', $channel_id)->first();
            } elseif(isset($item->channel_sku_id)) {
                $channel_sku = ChannelSKU::where('channel_sku_id', '=', $item->channel_sku_id)->where('channel_id', '=', $channel_id)->first();
            }  else {
                $channel_sku = ChannelSKU::leftjoin('sku', 'sku.sku_id', '=', 'channel_sku.sku_id')
                        ->where('sku.hubwire_sku', $item->sku_ref_id)
                        ->where('channel_sku.channel_id', $channel_id)->first();
            }

            $item->ref_id = $channel_sku->channel_sku_id;
            $item->unit_price = $channel_sku->channel_sku_price;
            $item->sale_price = $channel_sku->channel_sku_live_price;
            $item->merchant_id = $channel_sku->product->merchant_id;
            $item->created_at = Carbon::now()->setTimezone('UTC');
            $item->updated_at = $order->created_at;

            if($order->status > static::$failedStatus  && empty($item->fulfilled_channel) && $item->quantity > 0)
            {
                // Deduct the Channel SKU quantity
                // Log::info(print_r($channel_sku, true));
                // $oldQuantity = $channel_sku->channel_sku_quantity;
                // $channel_sku->decrement('channel_sku_quantity', $item->quantity);
                // $channel_sku->touch();
                event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $item->quantity, 'Order', $order->id, 'decrement'));
                $item->fulfilled_channel = $channel_id;
                $order->reserved_date = date('Y-m-d H:i:s');
                $order->save();
            }

            unset($item->channel_sku_ref_id);
            unset($item->channel_sku_id);
            unset($item->sku_ref_id);
            unset($item->channel_sku_id);
            unset($item->product_name);

            $returnLogData = '';
            if (isset($item->returnLogData)) {
            	$returnLogData = $item->returnLogData;
            	unset($item->returnLogData);
            }

            $item->order_id = $order->id;
            $item->save();
            // $order->items()->save($item);

            if (!empty($returnLogData)) {
            	$returnLog = [
					'item_id' 		=> $item->id,
					'user_id' 		=> $userId,
					'remark'		=> $returnLogData['remark'],
					'ref_id'		=> $returnLogData['ref_id']
				];

	    		self::processCancelReturn($item, $returnLog, $returnLogData['restock']);
            }

            $itemMerchants[$channel_sku->product->merchant_id] = $channel_sku->product->merchant_id;
        }

        foreach ($itemMerchants as $itemMerchantId) {
            $getRate = Order::getShippingRateDetails($order, $channel, $itemMerchantId);
            if (!empty($getRate)) {
                Order::calculateShippingFee($getRate, $order->id, $itemMerchantId);
            }
        }

        if($order->tp_source == 'manual')
            Activity::log('New manual order ('. $order->id .') has been created.', 0);
        else
            Activity::log('New order ('. $order->id .') has been created.', 0);

        // Create new order_status_log
        $statusLog = new OrderStatusLog(['user_id' => $userId, 'from_status' => '', 'to_status' => $order->getStatusName(), 'created_at' => Carbon::now()]);
        $log = $order->statusLog()->save($statusLog);

        // If to_status is completed and shipped_date is empty, get created_at date of completed status log and save it into order's shipped date.
        if($order->getStatusName() == 'Completed' && is_null($order->shipped_date)){
            $order->shipped_date = $log->created_at;
            $order->save();
        }

        $order_proc = new OrderProc($channel->id, new $channel->channel_type->controller, $order->id);
        // if order is paid and not yet shipped
        if ($order->status > static::$failedStatus && $order->status < static::$shippedStatus) {
            $order_proc->incrementReservedQuantity();
        }

        // DB::commit();

        $response['order_id'] = $order->id;
        $response['success'] = true;
        $order->updateElasticSearch();

        return $response;
    }

    public function updateOrder($data) {
    	$order = $this;
    	$response['order_id'] = $order->id;
        $response['update'] = true;

    	// update order
    	$orderProc = new OrderProc;
    	$userId = isset($data['user_id']) ? $data['user_id'] : 0;

    	if ($order->status != $data['order']->status) {
    		$orderData['status'] = $data['order']->status;
    	}

    	if ($order->paid_status != $data['order']->paid_status) {
    		$orderData['paid_status'] = $data['order']->paid_status;
    	}

    	if ($order->cancelled_status != $data['order']->cancelled_status) {
    		$orderData['cancelled_status'] = $data['order']->cancelled_status;
    	}

    	if (!empty($data['consignments'])) {
    		$consignmentNumbers = !empty($order->consignment_no) ? explode(',', $order->consignment_no) : [];

    		if (!empty($data['consignments']['failed'])) {
	    		foreach ($consignmentNumbers as $key => $value) {
		    		if (in_array($value, $data['consignments']['failed'])) {
		    			unset($consignmentNumbers[$key]);
		    		}
			 	}

			 	$consignmentNumbers = array_values($consignmentNumbers);
	    	}

		 	if (!empty($data['consignments']['success'])) {
		 		foreach ($data['consignments']['success'] as $val) {
			 		if (!in_array($val, $consignmentNumbers) && !empty($val)) {
			 			$consignmentNumbers[] = $val;
			 		}
			 	}
		 	}

		 	$orderData['consignment_no'] = implode(',', $consignmentNumbers);
		 	$orderData['shipping_notification_date'] = !empty($data['shipping_notification_date']) ? $data['shipping_notification_date'] : NULL;
    	}

    	if (isset($data['order']->partially_fulfilled)) {
    		$orderData['partially_fulfilled'] = $data['order']->partially_fulfilled;
    	}

    	$updateOrderResponse = json_decode($orderProc->updateOrderDetails($order->id, $orderData, $userId)->getContent(), true);

    	if (!$updateOrderResponse['success']) {
    		$response['success'] = false;
        	return $response;
    	}

    	if (!empty($data['fulfillments'])) {
    		foreach ($data['fulfillments'] as $tpItemId => $fulfillment) {
				$verifiedCount = $order->itemSKUs()->where('tp_item_id', '=', $tpItemId)
									->where('status', '=', 'Verified')
									->count();

				$newItems = $order->itemSKUs()->where('tp_item_id', '=', $tpItemId)
											->where(function ($query) {
    											$query->whereNotIn('status', ['Verified', 'Returned', 'Cancelled'])
    													->orWhereNull('status');
    										})
											->get();

				foreach ($newItems as $item) {
					if ($verifiedCount < $fulfillment['total_quantity']) {
						$item->status = 'Verified';
						$item->save();

						$eventInfo = array(
				            'fromStatus' => $item->status,
				            'toStatus' => $item->status,
				        );
				        Event::fire(new OrderUpdated($order->id, 'Item Status Updated', 'order_items', $item->id, $eventInfo, $userId));

						$verifiedCount++;
					}
					else {
						break;
					}
				}
			}
    	}

    	if (!empty($data['returns'])) {
    		foreach ($data['returns'] as $tpItemId => $returns) {
    			foreach ($returns as $return) {
    				$item = $order->itemSKUs()->where('tp_item_id', '=', $tpItemId)
    										->where(function ($query) {
    											$query->whereNotIn('status', ['Returned', 'Cancelled'])
    													->orWhereNull('status');
    										})
	    									->first();

	    			if (is_null($item)) {
	    				continue;
	    			}

	    			$returnLogData = [
						'item_id' 		=> $item->id,
						'user_id' 		=> $userId,
						'remark'		=> $return['remark'],
						'ref_id'		=> $return['ref_id']
					];

		    		self::processCancelReturn($item, $returnLogData, $return['restock']);
    			}
    		}
    	}

        $response['success'] = true;
        return $response;
    }

    private static function processCancelReturn ($orderItem, $returnLogData, $restock = true) {
		// checking if the return (ref_id) has been processed cannot be done here as quantity can be more than 1
		// checking should be done in the channel type's respective repo

		$isCancel = ($orderItem->order->status == Order::$shippedStatus) ? false : true;

		$orderRepo = new OrderRepository(new Order, new Mailer);
		$returnLog = $orderRepo->processCancelReturn($returnLogData, $isCancel, 1, false, $restock, false);

		if ($returnLog instanceof ReturnLog) {
			if ($returnLog->status == 'In Transit' || !$restock) {
				$returnLogStatus = $restock ? 'Restocked' : 'Rejected';
				$returnRepo = new ReturnRepository(new ReturnLog);
				$returnRepo->update(['status' => $returnLogStatus], $returnLog->id);
			}
		}
    }

    public function toAPIResponse()
    {
        return $this->apiResponse($this);
    }
    public static function apiResponse($data, $criteria = null)
    {

        /*
        [member_id] => 0
        [payment_type] => ipay88
        [sale_total] => 30.48
        [sale_shipping] => 7.16
        [channel_id] => 9874
        [created_at] => 2016-05-19 04:17:30
        [updated_at] => 2016-05-19 04:17:30
        [sale_status] => paid
        [sale_address] => 403 Kaylee Land Suite 198
    	South Meagan, ND 86962-3810
        [sale_phone] => 946-226-1283 x721
        [sale_recipient] => Prof. Lysanne Waters
        [client_id] => 4538
        [ref_id] =>
        [order_code] =>
        [consignment_no] =>
        [notification_date] =>
        [sale_discount] => 0
        [shipping_no] => 68050333
        [extra] =>
        [rate] => 1
        [currency] =>
        [sync_status] => 0
        [fulfillment_status] => 0
        [distribution_ch_id] => 0
        [sale_postcode] => 20674-0073
        [sale_country] => Kyrgyz Republic
        */
        if (empty($data->toArray())) {
            return null;
        }

        $sales = $data;
        $single = false;

        if (empty($sales[0])) {
            $sales = [$sales];
            $single = true;
        }

        $result = array();
        foreach ($sales as $sale) {
            // $extra = unserialize($sale->extra);
            $response  = new \stdClass();
            $response->id = $sale->id;
            $response->order_number = $sale->tp_order_code;
            $response->order_date = $sale->tp_order_date;
            $response->total_price = floatval($sale->total);
            $response->total_discount = floatval($sale->total_discount);
            $response->shipping_fee = floatval($sale->shipping_fee);
            $response->currency = $sale->currency;
            $response->payment_type = $sale->payment_type;
            $response->status = $sale->getStatusName();
            $response->cancelled_status = ($sale->cancelled_status == true) ? 1 : 0;
            $response->created_at =  $sale->created_at;
            $response->updated_at = $sale->updated_at;

            $shipping = new \stdClass();
            $shipping->recipient = $sale->shipping_recipient;
            $shipping->phone = $sale->shipping_phone;
            $shipping->tracking_no = $sale->consignment_no;
            $shipping->shipping_provider = $sale->shipping_provider;
            $shipping->address_1 = $sale->shipping_street_1;
            $shipping->address_2 = $sale->shipping_street_2;
            $shipping->city = $sale->shipping_city;
            $shipping->postcode = $sale->shipping_postcode;
            $shipping->state = $sale->shipping_state;
            $shipping->country = $sale->shipping_country;

            $response->shipping_info = $shipping;

            $response->items = $sale->items->toAPIResponse();

            if (!empty($sale->member)) {
                $response->customer = $sale->member->toAPIResponse();
            }
            $result[] = $response;
        }
        return $single?$result[0]:$result;
    }

    public function getStatusCodeByName($status)
    {
        return static::$statusCode[$status];
    }

    public static function getStatusCode()
    {
        return static::$statusCode;
    }

    public static function boot()
    {
        parent::boot();

        Order::created(function($model){
            $webhook = Webhook::where('channel_id','=',$model->channel_id)
                        ->where('type','=',1)->where('topic','=','sales/created')
                        ->first();
            if(!is_null($webhook))
            {
                $sync = new SyncRepository;
                $sync->orderCreated($model->id);
            }
            //$model->updateElasticSearch();
        });

        Order::updated(function($model){
            $webhook = Webhook::where('channel_id','=',$model->channel_id)
                        ->where('type','=',1)->where('topic','=','sales/updated')
                        ->first();
            if(!is_null($webhook))
            {
                $sync = new SyncRepository;
                $sync->orderUpdated($model->id);
            }
            $model->updateElasticSearch();
            //\Log::info($model->tp_order_date);
        });
    }

    public function getShippingNotificationDateAttribute($value)
    {
        if(is_null($value)){
            return $value;
        }
        else{
            try{
                if(session()->has('user_timezone')){
                    $adminTz = session('user_timezone');
                }else{
                    $userId = Authorizer::getResourceOwnerId();
                    $adminTz = User::where('id', '=', $userId)->value('timezone');
                    session(['user_timezone' => $adminTz]);
                }
                if($this->attributes['shipping_notification_date'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }

    public function getPaidDateAttribute($value)
    {
        if(trim($value) == '0000-00-00 00:00:00' || is_null($value))
        {
            return null;
        }
        else{
            try{
                if(session()->has('user_timezone')){
                    $adminTz = session('user_timezone');
                }else{
                    $userId = Authorizer::getResourceOwnerId();
                    $adminTz = User::where('id', '=', $userId)->value('timezone');
                    session(['user_timezone' => $adminTz]);
                }
                if($this->attributes['paid_date'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }

    public function getTpOrderDateAttribute($value)
    {
        if(trim($value) == '0000-00-00 00:00:00' || is_null($value))
        {
            return null;
        }
        else{
            try{
                if(session()->has('user_timezone')){
                    $adminTz = session('user_timezone');
                }else{
                    $userId = Authorizer::getResourceOwnerId();
                    $adminTz = User::where('id', '=', $userId)->value('timezone');
                    session(['user_timezone' => $adminTz]);
                }
                if($this->attributes['tp_order_date'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }

    public function getShippedDateAttribute($value)
    {
        if(is_null($value)){
            return $value;
        }
        else{
            try{
                if(session()->has('user_timezone')){
                    $adminTz = session('user_timezone');
                }else{
                    $userId = Authorizer::getResourceOwnerId();
                    $adminTz = User::where('id', '=', $userId)->value('timezone');
                    session(['user_timezone' => $adminTz]);
                }
                if($this->attributes['shipped_date'] != '0000-00-00 00:00:00')
                    return Helper::convertTimeToUserTimezone($value, $adminTz);
                else
                    return $value;
            }catch(NoActiveAccessTokenException $e){
                return $value;
            }
        }
    }

    public static function getStatusList($status)
    {
        $statusArr = array();
        $array = static::$statusCode;
        //$currentKey = $status;
        //$statusArr = [$array[$status] => $status];
        $statusArr =  [$status => array_search($status, $array)];
        $nextStatus = '';


        $currentKey = key($array);
        while ($currentKey !== null) {
            if($currentKey == 'New')
                $next = next($array);
            if($currentKey == array_search($status, $array)){
                $next = next($array);
                break;
            }
            $next = next($array);
            $currentKey = key($array);
        }

        return array_add($statusArr, $next, array_search($next, $array));

    }

    public function updateElasticSearch()
    {
        // \Log::info('Updating order #'.$this->id.' to ElasticSearch');
        /**
        ** Incase there's timezone conversion
        **/
        $adminTz = 'UTC';
        try{
            if(session()->has('user_timezone')){
                $adminTz = session('user_timezone');
            }else{
                $userId = Authorizer::getResourceOwnerId();
                $adminTz = User::where('id', '=', $userId)->value('timezone');
            }
        }
        catch(NoActiveAccessTokenException $e)
        {

        }

        if (!$this->relationLoaded('items')) $this->load('items');
        if (!$this->relationLoaded('channel')) $this->load('channel');
        if (!$this->relationLoaded('member')) $this->load('member');
        $doc = $this->toArray();

        $doc['paid_date'] = !empty($this->paid_date)?Helper::convertTimeToUTC($this->paid_date, $adminTz):null;
        $doc['tp_order_date'] = !empty($this->tp_order_date)?Helper::convertTimeToUTC($this->tp_order_date, $adminTz):$this->tp_order_date;
        $doc['shipping_notification_date'] = !empty($this->shipping_notification_date)?Helper::convertTimeToUTC($this->shipping_notification_date, $adminTz):$this->shipping_notification_date;

        $items = $this->items()->with('merchant')->get();

        $order_item = array();
        foreach($items as $tmp)
        {
            $item = $tmp->toArray();
            unset($item['order']);
            $order_item[] = $item;
        }

        $doc['member_name'] = !empty($this->member->member_name)?$this->member->member_name:'';
        $doc['item_quantity'] = $this->item_quantity;
        $doc['original_quantity'] = $this->original_quantity;
        $doc['items'] = $order_item;

        $params = array();
        $params['index'] = env('ELASTICSEARCH_ORDERS_INDEX','orders');
        $params['type']  = 'sales';
        $params['id']    = $doc['id'];
        if (!is_string($doc['updated_at'])) {
            $doc['updated_at'] = $doc['updated_at']->toDateTimeString();;
        }
        if (!is_string($doc['created_at'])) {
            $doc['created_at'] = $doc['created_at']->toDateTimeString();;
        }
        if (isset($doc['shipped_date']) && !is_string($doc['shipped_date'])) {
            $doc['shipped_date'] = $doc['shipped_date']->toDateTimeString();;
        }
        $doc['channel']['issuing_company'] = null;//$doc['channel']['issuing_company']['id'];

        $exists = \Es::exists($params);
        if(intval($exists)!==0)
            $params['body']['doc']  = $doc;
        else
            $params['body']  = $doc;

        return $exists? (\Es::update($params)) : (\Es::create($params));
    }

    public function getItemQuantityAttribute()
    {
        return $this->items()->sum('quantity');

    }

    public function getOriginalQuantityAttribute()
    {
        return $this->items()->sum('original_quantity');
    }

    public static function getShippingRateDetails($order, $channel, $merchant_id)
    {
        $rate = array();
        $result = array();
        $googleapisResponce = '';
        $other = array_merge(['All' => 'All', 'Other' => 'Other'], config('globals.malaysia_region'));
        $googleapisUrl = 'https://maps.googleapis.com/maps/api/geocode/json?address=';
        //https://maps.googleapis.com/maps/api/geocode/json?address=94300+malaysia
        $wholeMalaysia = array_merge(config('globals.west_malaysia_state'), config('globals.east_malaysia_state'));
        $getRate = empty($channel->channel_detail->shipping_rate) ? array() : json_decode($channel->channel_detail->shipping_rate, true);

        //\Log::info($getRate);dd(1);
        //cover the unknow rate lacation
        // foreach ($getRate as $key => $rateArray) {
        //     //if the location is not in the config list
        //     if (!in_array($rateArray['location'], array_merge($wholeMalaysia, config('countries'), $other))) {
        //         $optionCountry = preg_replace('/\s+/', '%20', $rateArray['location']);
        //         $googleapisResponce = json_decode(file_get_contents($googleapisUrl.$optionCountry), true);
        //         if ($googleapisResponce['status'] == 'OK') {
        //             foreach ($googleapisResponce['results'][0]['address_components'] as $orderAddress) {
        //                 if ($orderAddress['types'][0] == 'country') {
        //                     $getRate[$key]['location'] = $orderAddress['long_name'];
        //                 }
        //             }
        //         }
        //     }
        // }

        //cover the rate map
        foreach ($getRate as $rateArray) {
            //the set merchant logic
            if (!isset($rate[$rateArray['location']]['status'])     && !empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) {
                //\Log::info($rateArray['location'].' 1');
                $rate[$rateArray['location']]['status'] = true;
            }elseif (!isset($rate[$rateArray['location']]['status'])&& !empty($rateArray['shipping_merchant']) && !in_array($merchant_id, $rateArray['shipping_merchant'])) {
                //\Log::info($rateArray['location']. ' 2');
                $rate[$rateArray['location']]['status'] = false;
            }elseif (isset($rate[$rateArray['location']]['status']) && !empty($rateArray['shipping_merchant']) && !in_array($merchant_id, $rateArray['shipping_merchant'])) {
                //\Log::info($rateArray['location']. ' 3');
                $rate[$rateArray['location']]['status'] = false;
            }elseif(isset($rate[$rateArray['location']]['status'])  && !empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) {
                //\Log::info($rateArray['location']. ' 4');
                $rate[$rateArray['location']]['status'] = true;
            }elseif(!isset($rate[$rateArray['location']]['status']) &&  empty($rateArray['shipping_merchant']) && !isset($rate[$rateArray['location']]['base_amount'])) {
                //\Log::info($rateArray['location']. ' 5');
                $rate[$rateArray['location']]['status'] = true;
            }elseif(!isset($rate[$rateArray['location']]['status']) &&  empty($rateArray['shipping_merchant']) && isset($rate[$rateArray['location']]['base_amount'])) {
                //\Log::info($rateArray['location']. ' 6');
                $rate[$rateArray['location']]['status'] = false;
            }elseif(isset($rate[$rateArray['location']]['status'])  &&  empty($rateArray['shipping_merchant']) && !isset($rate[$rateArray['location']]['base_amount'])) {
                //\Log::info($rateArray['location']. ' 7');
                $rate[$rateArray['location']]['status'] = true;
            }

            //fix in the correct rate to the location
            if ($rateArray['location'] == 'West Malaysia' && $rate['West Malaysia']['status']) {
                foreach (config('globals.west_malaysia_state') as $value) {
                    if(isset($rate[$value]['status']) && !$rate[$value]['status']) break;
                    $rate[$value]['base_amount']        = $rateArray['base_amount'];
                    $rate[$value]['base_grams']         = $rateArray['base_grams'];
                    $rate[$value]['increment_amount']   = $rateArray['increment_amount'];
                    $rate[$value]['increment_grams']    = $rateArray['increment_grams'];
                }
                $rate['West Malaysia']['status'] = (!empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) ? false : true;
                $rate['West Malaysia']['base_amount'] = 0;
            }elseif ($rateArray['location'] == 'East Malaysia'  && $rate['East Malaysia']['status']) {
                foreach (config('globals.east_malaysia_state') as $value) {
                    if(isset($rate[$value]['status']) && !$rate[$value]['status']) break;
                    $rate[$value]['base_amount']        = $rateArray['base_amount'];
                    $rate[$value]['base_grams']         = $rateArray['base_grams'];
                    $rate[$value]['increment_amount']   = $rateArray['increment_amount'];
                    $rate[$value]['increment_grams']    = $rateArray['increment_grams'];
                }
                $rate['East Malaysia']['status']        = (!empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) ? false : true;
                $rate['East Malaysia']['base_amount'] = 0;
            }elseif ($rateArray['location'] == 'Other' && $rate['Other']['status']) {
                $rate['Other']['base_amount']       = $rateArray['base_amount'];
                $rate['Other']['base_grams']        = $rateArray['base_grams'];
                $rate['Other']['increment_amount']  = $rateArray['increment_amount'];
                $rate['Other']['increment_grams']   = $rateArray['increment_grams'];
                $rate['Other']['status']            = (!empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) ? false : true;

            }elseif ($rateArray['location'] == 'All' && $rate['All']['status']) {
                $rate['All']['base_amount']         = $rateArray['base_amount'];
                $rate['All']['base_grams']          = $rateArray['base_grams'];
                $rate['All']['increment_amount']    = $rateArray['increment_amount'];
                $rate['All']['increment_grams']     = $rateArray['increment_grams'];
                $rate['All']['status']              = (!empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) ? false : true;

            }elseif ($rate[$rateArray['location']]['status']) {
                $rate[$rateArray['location']]['base_amount']        = $rateArray['base_amount'];
                $rate[$rateArray['location']]['base_grams']         = $rateArray['base_grams'];
                $rate[$rateArray['location']]['increment_amount']   = $rateArray['increment_amount'];
                $rate[$rateArray['location']]['increment_grams']    = $rateArray['increment_grams'];
                $rate[$rateArray['location']]['status']             = (!empty($rateArray['shipping_merchant']) && in_array($merchant_id, $rateArray['shipping_merchant'])) ? 0 : 1;

            }
        }
        $rate = array_change_key_case($rate, CASE_LOWER);
        if(isset($rate['all'])) $rate = ['all' => $rate['all']] + $rate;
        //\Log::info('$rate... '.print_r($rate, true));

        $foundRate = false;
        $order_fields = ['shipping_city', 'shipping_state', 'shipping_country'];

        foreach ($order_fields as $order_field) {
            foreach ($rate as $location => $r) {
                if (in_array($location, ['West Malaysia', 'East Malaysia', 'other'])) continue;
                if (($location == 'all' && isset($r['base_grams'])) || strtolower($location) == strtolower($order[$order_field])) {
                    $return['baseAmount']       = $r['base_amount'];
                    $return['baseGrams']        = $r['base_grams'];
                    $return['incrementAmount']  = $r['increment_amount'];
                    $return['incrementGrams']   = $r['increment_grams'];
                    $foundRate = true;
                    break;
                }
            }
            if($foundRate) break;
        }

        /*//if all have be set then will use it as a high priority
        if (isset($rate['all']['base_amount'])) {
            $return['baseAmount']       = $rate['all']['base_amount'];
            $return['baseGrams']        = $rate['all']['base_grams'];
            $return['incrementAmount']  = $rate['all']['increment_amount'];
            $return['incrementGrams']   = $rate['all']['increment_grams'];
            return $return;
        }

        //get the shipping rate which in malaysia
        foreach ($wholeMalaysia as $state) {
            if ($state == $order['shipping_city'] && isset($rate[$state])) {
                //\Log::info('state '.$state);
                $return['baseAmount']       = $rate[$state]['base_amount'];
                $return['baseGrams']        = $rate[$state]['base_grams'];
                $return['incrementAmount']  = $rate[$state]['increment_amount'];
                $return['incrementGrams']   = $rate[$state]['increment_grams'];
                break;
            }elseif ($state == $order['shipping_state'] && isset($rate[$state])) {
                //\Log::info('state '.$state);
                $return['baseAmount']       = $rate[$state]['base_amount'];
                $return['baseGrams']        = $rate[$state]['base_grams'];
                $return['incrementAmount']  = $rate[$state]['increment_amount'];
                $return['incrementGrams']   = $rate[$state]['increment_grams'];
                break;
            }
        }

        //get shipping rate with the list in $wholeMalysia
        if (!isset($return) && empty($return)) {
            foreach (config('countries') as $country) {
                if ($country == $order['shipping_country'] && isset($rate[$order['shipping_country']]['base_amount'])) {
                    //\Log::info('country '.$country);
                    $return['baseAmount']       = $rate[$country]['base_amount'];
                    $return['baseGrams']        = $rate[$country]['base_grams'];
                    $return['incrementAmount']  = $rate[$country]['increment_amount'];
                    $return['incrementGrams']   = $rate[$country]['increment_grams'];
                    break;
                }
            }
        }*/


        // can't find in the config list, need to call google api
        if (!isset($return) && empty($return)) {
            $country = preg_replace('/\s+/', '%20', $order['shipping_country']);
            $postcode = preg_replace(['/,+/', '/\s+/'] , '', $order['shipping_postcode']);
            $googleapisResponce = json_decode(file_get_contents($googleapisUrl.$postcode.'+'.$country), true);
            //dd($googleapisResponce['results'][0]['address_components']);
            if ($googleapisResponce['status'] == 'OK') {
                foreach ($googleapisResponce['results'][0]['address_components'] as $orderAddress) {
                    if ($orderAddress['types'][0] == 'administrative_area_level_1') {
                        $result['state'] = strtolower($orderAddress['long_name']);
                    }
                    if ($orderAddress['types'][0] == 'locality') {
                        $result['locality'] = strtolower($orderAddress['long_name']);
                    }
                    if ($orderAddress['types'][0] == 'country') {
                        $result['country'] = strtolower($orderAddress['long_name']);
                    }
                }
            }
            //set unknow order address shipping rate
            foreach ($result as $location) {
               if ( isset($rate[$location]['base_amount']) ) {
                    $return['baseAmount']       = $rate[$location]['base_amount'];
                    $return['baseGrams']        = $rate[$location]['base_grams'];
                    $return['incrementAmount']  = $rate[$location]['increment_amount'];
                    $return['incrementGrams']   = $rate[$location]['increment_grams'];
                }
            }
        }

        // if the retrun still not set then use other rate
        if (!isset($return) && isset($rate['other']['base_amount'])) {
            $return['baseAmount']       = $rate['other']['base_amount'];
            $return['baseGrams']        = $rate['other']['base_grams'];
            $return['incrementAmount']  = $rate['other']['increment_amount'];
            $return['incrementGrams']   = $rate['other']['increment_grams'];
        }

        if (!isset($return) || empty($rate) || empty($getRate)) {
            $return['baseAmount']       = 0.00;
            $return['baseGrams']        = 0.00;
            $return['incrementAmount']  = 0.00;
            $return['incrementGrams']   = 0.00;
        }

        return $return;

    }

    public static function calculateShippingFee($data, $orderId, $merchant_id, $recalculate = false)
    {
        //calculate merchant_shipping_fee
        $getOrderItems = array();
        $gramsPerMerchant = array();
        if($recalculate == true) {
            $getOrderItems = OrderItem::Chargeable()->where('order_id', '=', $orderId)->where('merchant_id', '=', $merchant_id)->get(['merchant_id', 'id', 'ref_id', 'ref_type'])->keyBy('id');
        }else {
            $getOrderItems = OrderItem::where('order_id', '=', $orderId)->where('merchant_id', '=', $merchant_id)->get(['merchant_id', 'id', 'ref_id', 'ref_type'])->keyBy('id');
        }

        foreach ($getOrderItems as $orderItemId => $getOrderItem) {
            $gramsPerMerchant[$getOrderItem->merchant_id][$orderItemId] = $getOrderItem['ref']['sku']['sku_weight'];
        }
        foreach ($gramsPerMerchant as $merchantId => $itemsWeight) {
            $itemsCount =count($itemsWeight);

            foreach ($itemsWeight as $itemId => $weight) {
                $amountPerItem = 0;

                if ($itemsCount == 1 && isset($data['baseAmount'])) {
                    if ($data['baseGrams'] >= $weight) {
                        OrderItem::where('id', '=', $itemId)->update(['merchant_shipping_fee' => number_format($data['baseAmount'], 2)]);
                        \Log::info('order_item('.$itemId.') table merchant_shipping_fee column updated to Rm'.number_format($data['baseAmount'], 2));
                    }else{
                        $extraGrams = $weight - $data['baseGrams'];
                        $extraCount = ($data['incrementGrams'] > 0) ? $extraGrams/$data['incrementGrams'] : 0;
                        $amountPerItem += $data['baseAmount'];
                        for ($i=0; $i < $extraCount; $i++) {
                            $amountPerItem += $data['incrementAmount'];
                        }
                        OrderItem::where('id', '=', $itemId)->update(['merchant_shipping_fee' => number_format($amountPerItem, 2)]);
                        \Log::info('order_item('.$itemId.') table merchant_shipping_fee column updated to Rm'.number_format($amountPerItem, 2));
                    }
                }elseif ($itemsCount > 1 && isset($data['baseAmount'])) {
                    $totalWeight = array_sum($itemsWeight);
                    if ($data['baseGrams'] >= $totalWeight) {
                        $amountPerItem = $data['baseAmount']*$weight/$totalWeight;
                        OrderItem::where('id', '=', $itemId)->update(['merchant_shipping_fee' => number_format($amountPerItem, 2)]);
                        \Log::info('order_item('.$itemId.') table merchant_shipping_fee column updated to Rm'.number_format($amountPerItem, 2));
                    }else{
                        $extraGrams = $totalWeight - $data['baseGrams'];
                        $extraCount = ($data['incrementGrams'] > 0) ? $extraGrams/$data['incrementGrams'] : 0;
                        $amountPerItem += $data['baseAmount'];
                        for ($i=0; $i < $extraCount; $i++) {
                            $amountPerItem += $data['incrementAmount'];
                        }
                        $amountPerItem = $amountPerItem*$weight/$totalWeight;
                        OrderItem::where('id', '=', $itemId)->update(['merchant_shipping_fee' => number_format($amountPerItem, 2)]);
                        \Log::info('order_item('.$itemId.') table merchant_shipping_fee column updated to Rm'.number_format($amountPerItem, 2));
                    }
                }
            }
        }
    }
}
