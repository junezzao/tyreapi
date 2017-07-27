<?php

namespace App\Modules\ThirdParty\Repositories;


use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Message\Response as GuzzleResponse;
use GuzzleHttp\Exception\RequestException as RequestException;
use App\Modules\ThirdParty\Helpers\DateTimeUtils as DateTimeUtils;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use Validator;
use DateTime;
use App\Modules\ThirdParty\Config;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Member;
use Log;

class SellerCenterRepo
{
	 /**
     * Function to generate url string
     * 1 - Add timestamp and version
     * 2 - Urlencode
     * 3 - Generate signature based on sha256 format
     *
     * @param  array $url_params
     * @return string   returns a query string
     */
    public static function generateUriString($url_params, $user, $key)
    {
        $now = new DateTime();
        $url_params['Timestamp'] = $now->format(DateTime::ISO8601);
        $url_params['Version'] = '1.0';
        $url_params['UserID'] = $user;
        ksort($url_params);
        $parameters = array();
        foreach($url_params as $name=>$value)
        {
            $parameters[] = rawurlencode($name).'='.rawurlencode($value);
        }
        $strtosign = implode('&',$parameters);
        $url_params['Signature'] = rawurlencode(hash_hmac('sha256', $strtosign, $key, false));

        return http_build_query($url_params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function guzzleRequest($request_type, $url, $body = array())
    {
        try
        {
            $guzzle = new Guzzle();

            $response = $guzzle->request($request_type, $url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $body,
            ]);

            //handle later
            //handle error if json is not found
            if ($response->getHeader('Content-Type')[0]!='application/json' && $response->getHeader('Content-Type')[0]!='text/json')
            {
                $error_detail = method_exists($response,'getBody') && method_exists($response->getBody(),'getContents') ? $response->getBody()->getContents() : $response;
				$error = array();
				$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
				$error['error_desc'] = 'Request Failed , please check marketplace api settings . Response: '. $error_detail;
				return MpUtils::errorResponse($error, __METHOD__, __LINE__);
            }

            $response = json_decode($response->getBody()->getContents(), true);
            $response = self::processResponse($response['SuccessResponse']);
            return $response;
        }
        catch(RequestException $e)
        {
           	$error = array();
			$error['error_desc'] = "RequestException : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
        catch(Exception $e)
        {
            $error = array();
			$error['error_desc'] = "Exception : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
    }

    /**
     * Process the response array from Seller Center
     * @param array $response
     * @return array
     */
    public static function processResponse($response)
    {
        $return = array();
        if(isset($response['Head']['ErrorMessage']))
        {
            $error = array();
			$error['error_desc'] = "RequestException : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
        else
        {
            $return['success'] = true;
            $return['status_code'] = MpUtils::getStatusCode('OK_STATUS');
            if(isset($response['Head']['RequestId']))
            {
                $response['Body']['RequestId'] = $response['Head']['RequestId'];
            }
            $return['body'] = $response['Body'];
        }

        return $return;
    }

    /**
     * Convert nested array of categories tree to Single level array
     * @param array $body
     * @param array $filter array('endLevel')
     * @return array(return){success, count , array(categories)}
     */
    public static function processCategories($body, $filter=array())
    {
        try
        {
            $categories = self::loopProcessCategories($body['Categories']['Category'], $filter);

            $dropdownList = array();
			foreach ($categories as $cat_id => $category) {
				$dropdownList[$cat_id] = $category['path'];
			}
			$return['success']= true;
			$return['count'] = count($categories);
			$return['categories'] = $dropdownList;
            return $return;
        }
        catch(Exception $ex)
        {
            $error = array();
			$error['error_desc'] = "RequestException : ". $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
    }
    /**
     * Recursive Loop to falten nested categories from lazada into single level array
     * @param   [type]  $categories [categories from Lazada]
     * @param   array   $filter     array('cascade','endLevel')
     * @param   string  $parentName Parent name for sub categories
     * @param   integer $parentId   Parent id for sub categories , parent id is defaulted 0 for top level categories
     * @param   integer $depth      depth within the category tree , begins with 1,2, 3.... n  where 1 refers to the top level categories
     * @return  array
     *
     * @author Yuki AY <yuki@hubwire.com>
     * @version 2.1
     */
    public static function loopProcessCategories($categories ,$filter =array(),$parentName ='',$parentId = 0, $depth = 1)
    {
        $return = array();

        foreach($categories as $category)
        {
            $base = array();
            $path = '';
            if (!empty($path =$parentName)) $path = $parentName.'/'; //concatenate with parent Name if it is available
            $path .=$category['Name'];
            $base[$category['CategoryId']] = array(
                'id' => $category['CategoryId'],
                'depth' => $depth,
                'displayName' => $category['Name'],
                'path' => $path,
                'globalID'=>$category['GlobalIdentifier'],
                'parentID' => $parentId,
            );
            if(!empty($category['Children']))
            {
                $nextDepth = $depth + 1;
                $children = $category['Children']['Category'];
                if(!empty($children['CategoryId'])) $children = array('0'=>$children);
                $nested = self::loopProcessCategories($children,$filter,$path,$category['CategoryId'],$nextDepth);
                if(!in_array('endLevel',$filter)) $return = $return + $base; //include categories which are not end level
                $return = $return + $nested; // combine with array from recursive function
            }
            else
            {
                $return = $return + $base; //return bottom most level
            }
        }
        return $return;
    }

	/**
 	 * Verify order request filters and convert to correct datetime format
 	 * @param int $type
 	 * @param object $param
 	 * @return object(return){success, array(result)}
 	 */
	public static function prepareOrderRequest($type, $param)
	{
		try
		{	
			if($type == MpUtils::getTypeCode('GET_ORDER')) {
				$return = array('success' => true);
				$param['startTime'] = self::convertToSellerCenterDateTime($param['startTime']);
				$param['endTime'] = self::convertToSellerCenterDateTime($param['endTime']);
				$return['param'] = $param;
				return $return;
			} else {
				$error = array();
				$error['error_desc'] = 'Invalid Param Type: '. $type;
				$error['status_code'] = MpUtils::getStatusCode('VALIDATION_ERROR');
				return MpUtils::errorResponse($error, __METHOD__, __LINE__);
			}
		}
		catch(Exception $ex)
		{
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	public static function processOrder($inputs, $channel)
	{
		try
		{
			$orders = array();
			$channel_id = $channel->id;
			$channel_type = $channel->channel_type->name;
			$extra_info = json_decode($channel->channel_detail->extra_info, true);
			foreach($inputs as $input)
			{
				$order_ref_id = $input['OrderId'];

				// calculate cumulative fields
				$total = array(
					'Paid' => 0,
					'Shipping' => 0,
					'SaleAmount' => 0,
					'WalletCredits' => 0
				);

				foreach($input['OrderItems'] as $orderItem){
					$total['Paid'] += self::removeThousandSeparator($orderItem['PaidPrice']);
					$total['Shipping'] += self::removeThousandSeparator($orderItem['ShippingAmount']);
					$total['SaleAmount'] += self::removeThousandSeparator($orderItem['ItemPrice']);
					$total['WalletCredits'] += self::removeThousandSeparator($orderItem['WalletCredits']);
				}

				// create new order object
				$order = new Order;
				$order->total = self::removeThousandSeparator($input['Price']);
				$order->shipping_fee = self::removeThousandSeparator($total['Shipping']);
				$order->subtotal = $order->total - $order->shipping_fee;
				$order->total_discount = 0;

				$order->cart_discount = 0;
				$order->tp_order_id = $input['OrderId'];
				$order->tp_order_code = $input['OrderNumber'];
				$order->tp_order_date = self::convertToHWDateTime($input['CreatedAt']);
				$order->tp_source = 'auto';
				$order->status = Order::$newStatus;
				$order->paid_status = 1;
				$order->paid_date = $order->tp_order_date;
				$order->cancelled_status = 0;
				$order->payment_type = $input['PaymentMethod'];
				$order->shipping_recipient = $input['AddressShipping']['FirstName'].' '.$input['AddressShipping']['LastName'];
				$order->shipping_phone = $input['AddressShipping']['Phone'];
				$order->shipping_street_1 = $input['AddressShipping']['Address1'];
				$order->shipping_street_2 = $input['AddressShipping']['Address2'];
				$order->shipping_postcode = $input['AddressShipping']['PostCode'];
				$order->shipping_city = $input['AddressShipping']['City'];
				$order->shipping_state = '';
				$order->shipping_country = $input['AddressShipping']['Country'];
				$order->shipping_provider = $input['PaymentMethod'] == 'CashOnDelivery' ? $extra_info['shipping_provider_cod'] : $extra_info['shipping_provider'];			
				$order->consignment_no = '';
				$order->currency = 'MYR';
    			$order->forex_rate = 1;
				$order->tp_extra = json_encode(array(
					'Remarks' 					 => $input['Remarks'],
					'DeliveryInfo' 				 => $input['DeliveryInfo'],
					'GiftOption' 				 => $input['GiftOption'],
					'GiftMessage' 				 => $input['GiftMessage'],
					'WalletCredits' 			 => $total['WalletCredits'],
					'NationalRegistrationNumber' => $input['NationalRegistrationNumber'],
					'SaleAmount' 				 => $total['SaleAmount'],
					'created_at'				 => $order->tp_order_date
				));

				// create new member object
				$member = new Member;
				$member->member_name = $input['CustomerFirstName'].' '.$input['CustomerLastName'];
				$member->member_type = 1;
				$member->member_email = '';
				$member->member_mobile = $input['AddressBilling']['Phone'];

				$orders[$order_ref_id]['success'] = true;
				$orders[$order_ref_id]['order'] = $order;
				$orders[$order_ref_id]['member'] = $member;
			
				foreach($input['OrderItems'] as $orderItem){
					// create new order item object
					$item = new OrderItem;
					$item->ref_type = 'ChannelSKU';
					$item->sold_price = self::removeThousandSeparator($orderItem['ItemPrice']); // removed $orderItem['PaidPrice'] as it is affected by wallet credits
					$item->tax_inclusive = 1;
					$item->tax_rate = 0.06;
					$item->tax = round($item->sold_price/1.06*0.06, 2);
					$item->quantity = $item->original_quantity = 1;
					$item->discount = self::removeThousandSeparator($orderItem['ItemPrice']) - self::removeThousandSeparator($orderItem['PaidPrice']) + self::removeThousandSeparator($orderItem['WalletCredits']);
					$item->tp_discount = 0;
					$item->weighted_cart_discount = 0;
					$item->tp_item_id = $orderItem['OrderItemId'];
					$item->sku_ref_id = $orderItem['Sku'];
					$item->product_name = $orderItem['Name'];

					$orders[$order_ref_id]['items'][] = $item;
				}
			}

			return $orders;
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
 	 * Convert SellerCenter datetime to HW datetime designated for marketplace orders
 	 * @param $sellerCenter_date
 	 * @return $HW_date
 	 */
	public static function convertToHWDateTime($sellerCenter_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$sellerCenter_timezone = Config::get('sellerCenter.marketplace_timezone');
		$HW_date_format = Config::get('marketplace.std_date_format');

		return DateTimeUtils::convertTime($sellerCenter_date, $HW_date_format, $sellerCenter_timezone, $HW_timezone);
	}

	public static function zaloraPaymentType($payment_type, $channel_id) {
		$shippingProvider = (Config::get('zalora.default.ShippingProvider.'.$payment_type) != null) ? Config::get('zalora.default.ShippingProvider.'.$payment_type) : Config::get('zalora.default.ShippingProvider.others');

		return $shippingProvider;
	}

	public static function lazadaPaymentType($payment_type, $channel_id) {
		if($channel_id == 37 && $payment_type == 'CashOnDelivery') {
			return 'NinjaVan';
		}

                if(Config::get('lazada.default.ShippingProvider.'.$payment_type) != null) {
			$shippingProvider = Config::get('lazada.default.ShippingProvider.'.$payment_type);
		} else {
			if(Config::get('lazada.default.ShippingProvider.channels.'.$channel_id) != null) {
				$shippingProvider = Config::get('lazada.default.ShippingProvider.channels.'.$channel_id);
			} else {
				$shippingProvider = Config::get('lazada.default.ShippingProvider.others');
			}
		}

		return $shippingProvider;
	}

	/**
 	 * Convert HW datetime to SellerCenter datetime
 	 * @param $HW_date
 	 * @return $sellerCenter_date
 	 */
	public static function convertToSellerCenterDateTime($HW_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$sellerCenter_timezone = Config::get('sellerCenter.marketplace_timezone');
		$sellerCenter_date_format = Config::get('sellerCenter.queryStr_date_format');

		return DateTimeUtils::convertTime($HW_date, $sellerCenter_date_format, $HW_timezone, $sellerCenter_timezone);
	}

	public static function removeThousandSeparator($value){
		return str_replace(',', '',$value);
	}
}
