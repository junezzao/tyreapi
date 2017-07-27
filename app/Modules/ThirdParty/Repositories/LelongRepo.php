<?php

namespace App\Modules\ThirdParty\Repositories;

use App\Modules\ThirdParty\Helpers\DateTimeUtils as DateTimeUtils;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Config;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Member;

/**
 * The LelongRepo class contains helper methods to support
 * LelongController in doing internal tasks such as preparing formated data, validations etc
 *
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */

class LelongRepo
{
	public static function processOrder($data, $channel)
	{
		try
		{
			$response = array('success'=>true);
			$extra_info = json_decode($channel->channel_detail->extra_info, true);

			// get cart_discount
			$cart_discount = $data['coupondiscount'];

			// create new order object
			$order = new Order;
			$order->total = self::removeThousandSeparator($data['totalorderprice']);
			$order->shipping_fee = self::removeThousandSeparator($data['shippingfees']);
			$order->subtotal = $order->total - $order->shipping_fee;
			$order->total_discount = 0;
			$order->cart_discount = $cart_discount;
			$order->tp_order_id = $data['orderid'];
			$order->tp_order_code = $data['orderid'];
			$order->tp_order_date = self::convertToHWDateTime($data['orderdate']);
			$order->tp_source = 'auto';
			
			$order->status = (!empty($data['paymentreceiptid'])) ? Order::$newStatus : Order::$pendingStatus;
			$order->paid_status = (!empty($data['paymentreceiptid'])) ? 1 : 0;
			$order->paid_date = (!empty($data['paymentreceiptid'])) ? $order->tp_order_date : '';
			$order->cancelled_status = 0;
			$order->payment_type = $data['paymentmethod'];
			$order->shipping_recipient = $data['shippingname'];
			$order->shipping_phone = (!empty($data['shippingphone1']) && empty($data['shippingphone2'])) ? $data['shippingphone1'].', '.$data['shippingphone2'] : $data['shippingphone1'];
			$order->shipping_street_1 = $data['shippingaddress1'];
			$order->shipping_street_2 = $data['shippingaddress2'];
			$order->shipping_postcode = $data['shippingpostcode'];
			$order->shipping_city = $data['shippingcity'];
			$order->shipping_state = $data['shippingstate'];
			$order->shipping_country = $data['shippingcountry'];
			$order->shipping_provider = $extra_info['shipping_provider'];
			$order->consignment_no = '';
			$order->currency = 'MYR';
			$order->forex_rate = 1;
			$order->tp_extra = json_encode(array(
				'created_at' => $order->tp_order_date		
			));

			// create new member object
			$member = new Member;
            $member->member_name = $data['shippingname'];
            $member->member_type = 1;
            $member->member_email = $data['shippingemail'];
            $member->member_mobile = $order->shipping_phone;

			$response['order'] = $order;
			$response['member'] = $member;

			// create new order item object
			foreach ($data['ordereditem'] as $line_item) {
				for ($i=0; $i<self::removeThousandSeparator($line_item['quantity']); $i++) {
					// get weighted cart discount
					$weighted_cart_discount = round($cart_discount * self::removeThousandSeparator($line_item['priceperitem']) / $order->subtotal, 2);

					$item = new OrderItem;
					$item->ref_type = 'ChannelSKU';
					$item->sold_price = self::removeThousandSeparator($line_item['priceperitem']) - $weighted_cart_discount;
					$item->tax_inclusive = 1;
					$item->tax_rate = 0.06;
					$item->tax = round($item->sold_price/1.06*0.06, 2);
					$item->quantity = $item->original_quantity = 1;
					$item->discount = 0;
					$item->tp_discount = 0;
					$item->weighted_cart_discount = $weighted_cart_discount;
					$item->tp_item_id = '';
					$item->channel_sku_id = $line_item['usersku'];
					$item->product_name = '';

					$response['items'][] = $item;
				}
			}
	
			return $response;
		}
		catch(Exception $ex)
		{
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public static function processOrderUpdateRequest($data)
	{
		try
		{
			$return = array();

			$isPos = strcasecmp($data['source_name'], "pos") == 0;
			$HW_status = self::convertToHWStatus($data['financial_status'], $data['gateway'], $isPos);
			
			$fields = array();
			$fields['status'] = $HW_status['order_status'];
			$fields['paid_status'] = $HW_status['paid_status'];
			$fields['cancelled_status'] = $HW_status['cancelled_status'];

			$return['success'] = true;
			$return['fields'] = $fields;
	
			return $return;
		}
		catch(Exception $ex)
		{
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	/**
	 * Retrieve HW order status, paid_status and cancelled_status 
	 * based on Shopify financial_status, payment_type and store type (POS or webstore)
	 *
	 * Financial_status
	 * pending: The finances are pending.
	 * authorized: The finances have been authorized.
 	 * partially_paid: The finances have been partially paid.
	 * paid: The finances have been paid. (This is the default value.)
     * partially_refunded: The finances have been partially refunded.
	 * refunded: The finances have been refunded.
	 * voided: The finances have been voided.
     *
	 * @return array
	 */
	private static function convertToHWStatus($financial_status, $payment_type, $isPos)
	{
		$HW_status = array();
		$order_statuses = \Config::get('globals.order_status');
		switch($financial_status){
			case 'pending':
        	case 'authorized':
        	case 'partially_paid':
        		$HW_status['order_status'] = Order::$pendingStatus;
        		$HW_status['paid_status'] = 0;
        		$HW_status['cancelled_status'] = 0;
        		break;
        	case 'paid':
        	case 'partially_refunded':
        		$HW_status['order_status'] = ($isPos == 1) ? Order::$completedStatus : Order::$newStatus;
				$HW_status['paid_status'] = 1;
				$HW_status['cancelled_status'] = 0;
				break;
			case 'refunded':
			case 'voided':
				$HW_status['order_status'] = ($isPos == 1) ? Order::$completedStatus : Order::$newStatus;
				$HW_status['paid_status'] = 0;
				$HW_status['cancelled_status'] = 1;
				break;
			default:
				$HW_status['order_status'] = Order::$pendingStatus;
        		$HW_status['paid_status'] = 0;
        		$HW_status['cancelled_status'] = 0;
        		break;
		}

		// auto set COD to paid status
		if((strpos($payment_type, 'COD') !== false) && strcasecmp($financial_status, 'pending') == 0)
		{
			$HW_status['order_status'] = ($isPos == 1) ? $order_statuses['COMPLETED'] : $order_statuses['NEW'];
			$HW_status['paid_status'] = 1;
			$HW_status['cancelled_status'] = 0;
		}

		return $HW_status;
	}

	/**
 	 * Convert Lelong datetime to HW datetime designated for marketplace orders
 	 * @param $lelong_date
 	 * @return $HW_date
 	 */
	public static function convertToHWDateTime($lelong_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$lelong_timezone = Config::get('lelong.marketplace_timezone');
		$HW_date_format = Config::get('marketplace.std_date_format');

		return DateTimeUtils::convertTime($lelong_date, $HW_date_format, $lelong_timezone, $HW_timezone);
	}

	public static function removeThousandSeparator($value){
		return str_replace(',', '',$value);
	}
}