<?php

namespace App\Modules\ThirdParty\Repositories;

use App\Modules\ThirdParty\Helpers\DateTimeUtils as DateTimeUtils;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Repositories\Eloquent\SyncRepository;
use App\Events\ChannelSkuQuantityChange;
use App\Events\OrderUpdated;

use App\Modules\ThirdParty\Config;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Member;
use App\Models\Admin\ReturnLog;
use App\Models\Admin\ChannelSKU;

use Validator;
use DateTime;
use DB;
use Activity;
use Event;

/**
 * The ShopifyRepo class contains helper methods to support
 * ShopifyController in doing internal tasks such as preparing formated data, validations etc
 *
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */

class ShopifyRepo
{
	public static function processOrder($data, $channel)
	{
		try
		{
			$response = array('success'=>true);
			$extra_info = json_decode($channel->channel_detail->extra_info, true);

			// get shipping_fee
			$shipping_fee = 0;
			foreach($data['shipping_lines'] as $shippingItem)
			{
				$shipping_fee += self::removeThousandSeparator($shippingItem['price']);
			}

			// get cart_discount
			$isPos = strcasecmp($data['source_name'], "pos") == 0;
			$cart_discount = 0;
			$shipping_fee_discount = 0;
			$discountCodes = $data['discount_codes'];
			if(!$isPos && $discountCodes){
				// maybe need to handle promotions
				foreach ($discountCodes as $promo){
					if($promo['type'] == 'shipping')
						$shipping_fee_discount += self::removeThousandSeparator($promo['amount']);
					else
						$cart_discount += self::removeThousandSeparator($promo['amount']);
				}
			}else{
				if($discountCodes) $cart_discount = self::removeThousandSeparator($data['discount_codes'][0]['amount']);
			}

			// get address
			$address = '';
			if (!$isPos) {
				$address = !empty($data['shipping_address']) ? $data['shipping_address'] : '';
				$address = ((empty($address) || empty($address['address1'])) && !empty($data['billing_address'])) ? $data['billing_address'] : $address;
			}

			// get tp_extra
			$tp_extra = array();
			if($discountCodes) {
				$tp_extra['discount_codes'] = $data['discount_codes'][0]['code'];
				$tp_extra['discount_type'] = $data['discount_codes'][0]['type'];
			}
			$tp_extra['created_at'] = self::convertToHWDateTime($data['created_at']);

			// create new order object
			$order = new Order;
			$order->subtotal = self::removeThousandSeparator($data['subtotal_price']);
			$order->total = self::removeThousandSeparator($data['total_price']);
			$order->shipping_fee = $shipping_fee - $shipping_fee_discount;
			$order->total_discount = self::removeThousandSeparator($data['total_discounts']) - $shipping_fee_discount;
			$order->cart_discount = $cart_discount;
			$order->tp_order_id = $data['id'];
			$order->tp_order_code = $data['name'];
			$order->tp_order_date = self::convertToHWDateTime($data['created_at']);
			$order->tp_source = 'auto';
			$order->partially_fulfilled = ($data['fulfillment_status'] == 'partial') ? 1 : 0;

			$HW_status = self::convertToHWStatus($data['financial_status'], $data['gateway'], $isPos);

			if ($data['fulfillment_status'] == 'fulfilled' || $data['fulfillment_status'] == 'partial') {
				$HW_status['order_status'] = Order::$shippedStatus;
			}

			if (!is_null($data['cancelled_at'])) {
				$HW_status['cancelled_status'] = 1;
			}

			$order->status = $HW_status['order_status'];
			$order->paid_status = $HW_status['paid_status'];
			$order->paid_date = ($HW_status['paid_status'] == 1) ? self::convertToHWDateTime($data['processed_at']) : '';
			$order->cancelled_status = $HW_status['cancelled_status'];
			$order->payment_type = $data['gateway'] ? $data['gateway'] : 'Unknown';
			$order->shipping_recipient = (!$isPos && !empty($address)) ? $address['first_name'].' '.$address['last_name'] : '';
			$order->shipping_phone = (!$isPos && !empty($address) && !is_null($address['phone'])) ? $address['phone'] : '';
			$order->shipping_street_1 = (!$isPos && !empty($address) && !is_null($address['address1'])) ? $address['address1'] : '';

			if (!$isPos && !empty($address)) {
				$order->shipping_street_2 = $address['address2'];
				$order->shipping_postcode = $address['zip'];
				$order->shipping_city = $address['city'];
				$order->shipping_state = $address['province'];
				$order->shipping_country = $address['country'];
				$order->shipping_provider = isset($extra_info['shipping_provider']) ? $extra_info['shipping_provider'] : null;
			}

			// fulfillments
			$consignments = [];
			$fulfillments = [];
			$shippingNotificationDate = '';

			if (!empty($data['fulfillments']) && count($data['fulfillments']) > 0) {
				foreach ($data['fulfillments'] as $fulfillment) {
					if ($fulfillment['status'] == 'success') {
						// only take the first one
						if (empty($shippingNotificationDate) && !empty($fulfillment['created_at'])) {
							$shippingNotificationDate = self::convertToHWDateTime($fulfillment['created_at']);
						}

						foreach ($fulfillment['line_items'] as $item) {
							if (!empty($fulfillments[$item['id']])) {
								$fulfillments[$item['id']]['total_quantity'] += $item['quantity'];
							}
							else {
								$fulfillments[$item['id']] = [
									'total_quantity' => $item['quantity'],
									'quantity_processed' => 0
								];
							}
						}
					}

					if (!empty($fulfillment['tracking_number'])) {
						$status = ($fulfillment['status'] == 'success') ? 'success' : 'failed';

						$consignments[$status][] = $fulfillment['tracking_number'];
					}
				}

				$order->consignment_no = (!empty($consignments['success'])) ? implode(',', $consignments['success']) : '';
			}
			else {
				$order->consignment_no = '';
			}

			$response['consignments'] = $consignments;
			$response['shipping_notification_date'] = $shippingNotificationDate;

			$order->currency = 'MYR';
			$order->forex_rate = 1;
			$order->tp_extra = json_encode($tp_extra);

			// create new member object
			if (!$isPos && !empty($data['customer'])) {
				$customer = $data['customer'];
	            $default_address = isset($customer['default_address']) ? $customer['default_address'] : array();
	            $member = new Member;
	            $member->member_name = $customer['first_name'].' '.$customer['last_name'];
	            $member->member_type = 1;
	            $member->member_email = $data['email'];
	            $member->member_mobile = (isset($default_address['phone']) && !is_null($default_address['phone'])) ? $default_address['phone'] : '';

	            $response['member'] = $member;
			}

			$response['order'] = $order;

			// create new order item object
			$tax_inclusive = $data['taxes_included'];

			// returns
			$refundedTpItemIds = [];
			$refunds = [];
			if (!empty($data['refunds']) && count($data['refunds']) > 0) {
				foreach ($data['refunds'] as $refund) {
					foreach ($refund['refund_line_items'] as $refundItem) {
						for ($i = 0; $i < $refundItem['quantity']; $i++) {
							$refunds[$refundItem['line_item_id']][] = [
								'ref_id'		=> $refund['id'],
								'restock' 		=> $refund['restock'],
								'remark'		=> !empty($refund['note']) ? $refund['note'] : '',
							];
						}

						$refundedTpItemIds[] = $refundItem['line_item_id'];
					}
				}
			}

			$refundedTpItems = [];
			foreach ($data['line_items'] as $line_item) {
				if (!empty($refunds[$line_item['id']])) {
					$refundedTpItems[$line_item['id']] = [
						'total_quantity'		=> count($refunds[$line_item['id']]),
						'quantity_processed'	=> 0
					];
				}
			}

			foreach ($data['line_items'] as $line_item) {
				for ($i = 0; $i < self::removeThousandSeparator($line_item['quantity']); $i++) {
					$item = new OrderItem;
					$item->ref_type = 'ChannelSKU';

					// get total tax and tax rate
					$total_tax = 0;
					$tax_lines = $line_item['tax_lines'];
					$tax_rate = 0;
					foreach ($tax_lines as $tax) {
						$total_tax += self::removeThousandSeparator($tax['price']);
						$tax_rate = $tax['rate'];
					}

					$sold_price = self::removeThousandSeparator($line_item['price']) - (floatval(self::removeThousandSeparator($line_item['total_discount']))/floatval(self::removeThousandSeparator($line_item['quantity'])));
					// if tax exclusive
					if($tax_inclusive === false || $tax_inclusive == 0) {
						$tax_inclusive = false;
					}
					else { // if tax inclusive
						$tax_inclusive = true;
					}

					// $weighted_cart_discount = ($data['subtotal_price'] <= 0) ? 0 : round($cart_discount * $sold_price / $data['subtotal_price'], 2);
					$weighted_cart_discount = (self::removeThousandSeparator($data['subtotal_price']) <= 0 || $cart_discount <= 0) ? 0 : round($sold_price / ((self::removeThousandSeparator($data['subtotal_price']) / $cart_discount) + 1), 2);

					$item->sold_price = $sold_price - $weighted_cart_discount;
					$item->tax_inclusive = $tax_inclusive;
					$item->tax_rate = $tax_rate;
					$item->tax = floatval($total_tax)/floatval(self::removeThousandSeparator($line_item['quantity']));
					$item->quantity = $item->original_quantity = 1;
					$item->discount = self::removeThousandSeparator($line_item['total_discount']);
					$item->tp_discount = 0;
					$item->weighted_cart_discount = $weighted_cart_discount;
					$item->tp_item_id = $line_item['id'];
					$item->channel_sku_ref_id = $line_item['variant_id'];
					$item->product_name = $line_item['name'];

					if ($order->status == Order::$completedStatus || $line_item['fulfillment_status'] == 'fulfilled'
					    || ($line_item['fulfillment_status'] == 'partial' && !empty($fulfillments[$line_item['id']])
					         && $fulfillments[$line_item['id']]['quantity_processed'] < $fulfillments[$line_item['id']]['total_quantity'])) {
						$item->status = 'Verified';
						$fulfillments[$line_item['id']]['quantity_processed']++;
					}
					else {
						$item->status = null;
					}

					if (!empty($refundedTpItems[$line_item['id']]) && $refundedTpItems[$line_item['id']]['quantity_processed'] < $refundedTpItems[$line_item['id']]['total_quantity']) {
						$item->returnLogData = $refunds[$refundItem['line_item_id']][$refundedTpItems[$line_item['id']]['quantity_processed']];

						$refundedTpItems[$line_item['id']]['quantity_processed']++;
					}

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

	public static function processOrderUpdateRequest($data, $oriOrder, $channelType)
	{
		try
		{
			$return = array();
			$isPos = strcasecmp($data['source_name'], "pos") == 0;
			$HW_status = self::convertToHWStatus($data['financial_status'], $data['gateway'], $isPos);

			if ($data['fulfillment_status'] == 'fulfilled' || $data['fulfillment_status'] == 'partial') {
				$HW_status['order_status'] = Order::$shippedStatus;
			}

			if (!is_null($data['cancelled_at'])) {
				$HW_status['cancelled_status'] = 1;
			}

			if ($oriOrder->status >= Order::$shippedStatus) {
				$HW_status['order_status'] = $oriOrder->status;
			}

			$order = new Order;
			$order->status = $HW_status['order_status'];
			$order->paid_status = $HW_status['paid_status'];
			$order->cancelled_status = $HW_status['cancelled_status'];

			if ($order->paid_status) {
				$order->paid_date = self::convertToHWDateTime($data['updated_at']);
			}

			if ($channelType == 'Shopify POS') {
				$order->partially_fulfilled = ($data['fulfillment_status'] == 'partial') ? 1 : 0;

				// fulfillments
				if (!empty($data['fulfillments']) && count($data['fulfillments']) > 0) {
					$consignments = [];
					$fulfillments = [];
					$shippingNotificationDate = '';

					foreach ($data['fulfillments'] as $fulfillment) {
						if ($fulfillment['status'] == 'success') {
							// only take the first one
							if (empty($shippingNotificationDate) && !empty($fulfillment['created_at'])) {
								$shippingNotificationDate = self::convertToHWDateTime($fulfillment['created_at']);
							}

							foreach ($fulfillment['line_items'] as $item) {
								if (!empty($fulfillments[$item['id']])) {
									$fulfillments[$item['id']]['total_quantity'] += $item['quantity'];
								}
								else {
									$fulfillments[$item['id']] = [
										'total_quantity' => $item['quantity'],
										'quantity_processed' => 0
									];
								}
							}
						}

						if (!empty($fulfillment['tracking_number'])) {
							$status = ($fulfillment['status'] == 'success') ? 'success' : 'failed';

							$consignments[$status][] = $fulfillment['tracking_number'];
						}
					}

					$return['fulfillments'] = $fulfillments;
					if (!empty($consignments)) {
						$return['consignments'] = $consignments;
						$return['shipping_notification_date'] = $shippingNotificationDate;
					}
				}

				// returns
				$refunds = [];
				if (!empty($data['refunds']) && count($data['refunds']) > 0) {
					foreach ($data['refunds'] as $refund) {
						$returnLog = ReturnLog::where('order_id', '=', $oriOrder->id)
												->where('ref_id', '=', $refund['id'])
												->first();

						if (!is_null($returnLog)) {
							continue;
						}

						foreach ($refund['refund_line_items'] as $refundItem) {
							for ($i = 0; $i < $refundItem['quantity']; $i++) {
								$refunds[$refundItem['line_item_id']][] = [
									'ref_id'	=> $refund['id'],
									'restock' 	=> $refund['restock'],
									'remark'	=> !empty($refund['note']) ? $refund['note'] : '',
								];
							}
						}
					}

					$return['returns'] = $refunds;
				}
			}

			$return['success'] = true;
			$return['order'] = $order;

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

	public static function processRefund($data, $channelId) {
		try{
			$shopifyOrderId = $data['order_id'];
			$order = Order::where('tp_order_id', $shopifyOrderId)->where('channel_id', $channelId)->firstOrFail();

			$totalRefundedAmt = 0;
			$restock = ($data['restock'] > 0);

			DB::beginTransaction();
			foreach ($data['refund_line_items'] as $refundItem) {

				// split returned qty to 1
				for ($i=1; $i<=$refundItem['quantity']; $i++) {
					$returnedQty = 1;
					$refundedAmt = 0;
					$lineItem = $refundItem['line_item'];
					$channelSku = ChannelSKU::where('ref_id', $lineItem['variant_id'])->where('channel_id', $channelId)->firstOrFail();
					$orderItem = OrderItem::where('order_id', $order->id)
								->where('ref_type', 'ChannelSKU')
								->where('ref_id', $channelSku->channel_sku_id)
								->where(function($query) {
									$query->whereNotIn('status', ['Returned', 'Cancelled'])
										->orWhereNull('status');
								})
								->firstOrFail();

					$totalTax = 0;
					foreach ($lineItem['tax_lines'] as $tax) {
						$totalTax += $tax['price'];
					}

					// if not yet paid, do not credit back into channel sku table
					if ($order->paid_status) {
						if (!empty($orderItem->fulfilled_channel)) {
							$order_proc = new OrderProc();
				            $order_proc->processReturnforReservedQuantity($order->id, $orderItem->id, $returnedQty);
				            Activity::log('Reserved Quantity for channel_sku_id ('. $orderItem->ref_id .') has been decremented by '.$returnedQty, 0);

				            // credit returned qty back into channel_sku table
							$channelSku = ChannelSKU::where('ref_id', $lineItem['variant_id'])
										->where('channel_id', $orderItem->fulfilled_channel)->firstOrFail();
							// $oldQuantity = $channelSku->channel_sku_quantity;
							// $channelSku->increment('channel_sku_quantity', $returnedQty);
							// $channelSku->touch();
							$updateChnlSku = true;
						}

						// if restock never happen at Shopify side, force a updateQuantity sync
						if (!$restock) SyncRepository::updateQuantity( array('merchant_id'=>$order->merchant_id, 'channel_sku_id'=>$orderItem->ref_id) );

						$refundedAmt = ($lineItem['price'] * $refundItem['quantity']) - ($lineItem['total_discount'] / $lineItem['quantity'] * $returnedQty) + ($totalTax / $lineItem['quantity'] * $returnedQty);
					}

					$totalRefundedAmt += $refundedAmt;

					$log = new ReturnLog;
					$log->member_id = $order->member_id;
					$log->user_id = 0;
					$log->order_id = $order->id;
					$log->order_item_id = $orderItem->id;
					$log->quantity = $returnedQty;
					$log->refund_type = $order->payment_type;
					$log->amount = $refundedAmt;
					$log->status = 'Restocked';
					$log->remark = !empty($data['note']) ? $data['note'] : '';
					$log->ref_id = $data['id'];
					$log->completed_at = self::convertToHWDateTime($data['created_at']);
					$log->order_status = $order->getStatusName();
					$log->created_at = self::convertToHWDateTime($data['created_at']);
					$log->updated_at = self::convertToHWDateTime($data['created_at']);
					$log->save();

					// update order item
					$orderItem->quantity -= $returnedQty;
					$orderItem->status = 'Returned';
					$orderItem->save();
					Activity::log('Item ' . $orderItem->id . ' has been ' . $orderItem->status, 0);

					// fire order updated event to record order history
			        Event::fire(new OrderUpdated($orderItem->order_id, 'Returned Item: Restocked', 'return_log', $log->id, array('orderItemRefId'=>$orderItem->ref_id), 0));

					// fire channel_sku quantity event
					if($updateChnlSku) {
						// event(new ChannelSkuQuantityChange($channelSku->channel_sku_id, $oldQuantity, 'ReturnLog', $log->id));
						event(new ChannelSkuQuantityChange($channelSku->channel_sku_id, $returnedQty, 'ReturnLog', $log->id, 'increment'));
					}
				}
			}

			$order->refunded_amount = (!is_null($order->refunded_amount)) ? ($order->refunded_amount + $totalRefundedAmt) : $totalRefundedAmt;
			$order->updated_at = self::convertToHWDateTime($data['created_at']);
			$order->save();

			DB::commit();
			$response['success'] = true;
			return $response;
		}
		catch (Exception $e) {
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public static function processSyncRefund($data) {
		$log = ReturnLog::findOrFail($data['log_id']);

		$log->amount = $data['refunded_amount'];
		$log->ref_id = $data['refund_ref'];
		$log->updated_at = self::convertToHWDateTime($data['updated_at']);
		$log->save();

		$order = Order::findOrFail($log->order_id);

		if (!is_null($order->refunded_amount)) {
			$order->refunded_amount += $data['refunded_amount'];
		}
		else {
			$order->refunded_amount = $data['refunded_amount'];
		}

		$order->save();
	}

	public static function checkRefundProcessed($channelId, $shopifyOrderId, $refundId) {
		$order = Order::where('tp_order_id', '=', $shopifyOrderId)
						->where('channel_id', '=', $channelId)
						->firstOrFail();

		$log = ReturnLog::where('ref_id', '=', $refundId)->where('order_id', '=', $order->id)->first();

		return ($log) ? true : false;
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
			$HW_status['order_status'] = ($isPos == 1) ? Order::$completedStatus : Order::$newStatus;
			$HW_status['paid_status'] = 1;
			$HW_status['cancelled_status'] = 0;
		}

		return $HW_status;
	}

	/**
 	 * Convert Shopify datetime to HW datetime designated for marketplace orders
 	 * @param $shopify_date
 	 * @return $HW_date
 	 */
	public static function convertToHWDateTime($shopify_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$shopify_timezone = Config::get('shopify.marketplace_timezone');
		$HW_date_format = Config::get('marketplace.std_date_format');

		return DateTimeUtils::convertTime($shopify_date, $HW_date_format, $shopify_timezone, $HW_timezone);
	}

	public static function removeThousandSeparator($value){
		return str_replace(',', '',$value);
	}
}
