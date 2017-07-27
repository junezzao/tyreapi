<?php

namespace App\Modules\ThirdParty\Repositories;

use App\Modules\ThirdParty\Helpers\DateTimeUtils as DateTimeUtils;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Mappers\ElevenStMapper as Mapper;

use Exception;
use Validator;
use DateTime;
use App\Modules\ThirdParty\Config;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Member;

/**
 * The ElevenStreetRepo class contains helper methods to support
 * ElevenStreetController in doing internal tasks such as preparing formated data, validations etc
 *
 * @version   1.0
 * @author    Yuki Au Yong <yuki@hubwire.com>
 */

class ElevenStreetRepo
{
	/**
 	 * Validate Product data that it conforms to eleven street standards before sending to eleven Street API
 	 * @param object $product
 	 * @param int $type state the type if it is create/ update product/ update stock quantity
 	 * @return object(return){success, array(error)}
 	 */
	public static function verifyProduct($product,$type)
	{
		try{
			$config_types = MpUtils::getTypeCode();
			$err_status_code = MpUtils::getStatusCode('VALIDATION_ERROR');

			if(!is_object($product))
			{				
				return array('success' => false, 'error' => 'Verify Product Input is not an Object');
			}

			$product = MpUtils::objectToArray($product);
		
			if($type == $config_types['CREATE_PRODUCT']||$type == $config_types['UPDATE_PRODUCT'])
			{
				$rules = array(
					'product_code' => 'required',
					'thirdparty_category' => 'required',
					'product_name' => 'required',
					'product_brand' => '',
					'product_weight' => 'required|numeric|min:0.001', //min:value in kg
					'product_description' => 'required',
					'product_price' => 'required|numeric|min:0.01',
					'product_quantity' => 'integer',
					'product_images'=> 'required|array|min:1',
					'sell_method' => "in:01", // default 01
					'service_type' => "in:01,25",
					'item_condition' => "in:01",
					'allow_minors' => 'in:Y,N', // default 01
					'gst_applicable' => 'in:01,02,03,04', // default 01
					'gst_rate' => 'required_if:gst_applicable,01,04|numeric', // percentage can be float
					'origin_country' => 'in:01,02',
					'origin_country_code' => 'required_if:origin_country,02|integer',
					'display_reviews' => 'in:Y,N',
					'enable_reviews' => 'in:Y,N',
					'sales_period' => 'in:Y,N',
					'sales_period_code' => 'required_if:sales_period,Y|integer|in:0,3,5,7,15,30,60,90,120',
					'sales_period_start_date' => 'required_if:sales_period_code,0|date_format:"d/m/Y"', //string date
					'sales_period_end_date' => 'required_if:sales_period_code,0|date_format:"d/m/Y"', //string date
					'coupon' => 'in:NULL',
					'points' => 'in:NULL',
					'skus'=> 'required_with:option_title|array|min:1',
					'option_title'=>'required_with:skus',
					'shipping_method'=>'in:01,05',
					'delivery_type'=>'in:01,11,12',
					//'short_description' => 'String', //html
				);

				if($type == $config_types['UPDATE_PRODUCT'])$rules['product_ref_id'] = 'required';

				//custom errors
				$messages = array(
					'coupon.in' => 'The :attribute feature is not yet supported in Hubwire',
					'points.in' => 'The :attribute feature is not yet supported in Hubwire',
					'image' => 'The :attribute must be image content type (gif/jpg/jpeg)',
					'url'=>'The :attribute must be in url format'
				);

				//check if option price is +--% of product price
				$priceRangeRule = isset($product['product_price'])?('min:'.(-$product['product_price']*0.5).'|max:'.($product['product_price']*0.5)):'';
				

				if(!empty($product['skus'])){
					foreach($product['skus'] as $i => $sku)
					{
						$rules['skus.'.$i.'.quantity'] = 'required|integer|min:0';
						$rules['skus.'.$i.'.name'] = 'required';
						$rules['skus.'.$i.'.active'] = 'required|in:0,1';
						$rules['skus.'.$i.'.add_weight'] = 'numeric';

						$messages['skus.'.$i.'.active.in'] = 'The :attribute must be either 0 or 1 ';
					}
				}

				if(!empty($product['product_images'])){
					foreach($product['product_images'] as $k => $url)
					$rules['product_images.'.$k] = 'url';
				}
				
				$validator = \Validator::make( $product, $rules , $messages);
				
			}
			else if ($type == $config_types['STOCK_QTY_UPDATE']) {
				$rules = array(
					'quantity'=>'required|integer|min:0',
					'sku_ref_id'=>'required',
					'product_ref_id'=>'required',
				);
				$messages = array();
				$validator = \Validator::make( $product, $rules , $messages);
			}
			else {
				return array('success' => false, 'error' => 'Invalid Param Type :' . $type);
			}

			if ($validator->fails()) {	
				return array('success' => false, 'error' => $validator->messages()->toArray());
			}

			return array('success' => true);
		}
		catch(Exception $e)
		{
			$error = 'Status code: ' . MpUtils::getStatusCode('SERVER_ERROR');
			$error .= ' | Error: ' . $e->getMessage() . ' at ' . $e->getLine();
			$error .= ($e->hasResponse()) ? ' | Response: ' . json_encode($e->getResponse()) : '';

			return array('success' => false, 'error' => $error);
		}
	}

	/**
	* Massage data for 11street and Assign hardcoded values / set limits according
	* Default values from config/elevenStreet
	*
	* @param (object)$product
	* @return (object)$product
	*/
	public static function prepareProduct($product)
	{
		//assign default fields
		$default_fields= Config::get('elevenSt.product_default') ;

		foreach($default_fields as $default_field=> $default_value)
		{
			if(!isset($product->{$default_field})) $product->{$default_field} = $default_value;
		}
		
		if(isset($product->product_images)){
			//check array size for Image, if more than 4 take top 4
			$image_limit = Config::get('elevenSt.image_limit');
			if( sizeof($product->product_images)>$image_limit){
				$product->product_images = array_slice($product->product_images,0,$image_limit);
			}

			//insert images into description
			foreach ($product->product_images as $image) {
				$product->product_description.='<p align="center"><img src="'.$image.'"></p>';
			}
		}

		if(isset($product->skus) && !empty($product->skus)){
			$product->option_selection_count = Config::get('elevenSt.num_of_option');
			$product->product_quantity = array_sum(array_pluck($product->skus, 'quantity'));

			foreach($product->skus as $sku)
			{
				if($sku->quantity==0){$sku->active = 0 ;} // set active as sold out if quantity is 0 else it will throw 11street error
				$sku->active =($sku->active ==0)? '02':'01';

				//default to 0 if not set
				$sku->add_price = isset($sku->add_price)?$sku->add_price:0;
				$sku->add_weight = isset($sku->add_weight)?$sku->add_weight:0;
			}
		}

		$return['success']= true;
		$return['product']= $product;
		return $return;
	}

	/*
	*	Convert Product Data to (Array)<11street product xml format>
	*	@param (object)$product
	*	return (object)$product
	*/
	public static function convertToArray($product)
	{
		//customize fields
		if(isset($product->product_name))
		{
			$product->product_name = "{$product->product_brand} {$product->product_name}";
		}

		$mp_product = array();
		$mp_product = Mapper::mapToMarketplaceFields($product);
		unset($mp_product['skus'], $mp_product['product_images']);

		//currently unnecessary
		//if($mp_product['dlvCstInstBasiCd'] ==11) $mp_product['productDlvPrmt']= array('dlvPrmtCd' => 'NA'); //Shipping promo

		if(isset($product->product_images)){
			for ($i = 1, $c = count($product->product_images); $i <= $c; $i++)
			{
				$mp_product["prdImage0{$i}"] = $product->product_images[$i-1];
			}
		}
		if(isset($product->skus)){
			foreach($product->skus as $sku)
			{
				$option['colCount'] =$sku->quantity;
				$option['colValue0'] = $sku->name;
				$option['optPrc'] = isset($sku->add_price)?$sku->add_price:0;
				$option['optStatus'] = $sku->active;
				$option['optWght'] =  isset($sku->add_weight)?$sku->add_weight:0;
				if(isset($sku->useYn)) {
					$option['useYn'] = $sku->useYn;
				}

				$mp_product["productSelOption"][] = $option;
			}
		}

		if(isset($product->optWrtCnt)){
			$mp_product['optWrtCnt'] 			= $product->optWrtCnt;
			$mp_product["productWrtOption"] 	= $product->productWrtOption;
		}

		if(isset($product->sku_ref_id)){
			// Quick fix, because the $mp_product = Mapper::mapToMarketplaceFields($product); returning
			// duplicate keys
			unset($mp_product['sku_ref_id'], $mp_product['quantity']);
			$mp_product['prdStckNo'] 		= $product->sku_ref_id;
			$mp_product['stckQty'] 			= $product->quantity;
		}

		return $mp_product;
	}

	/**
 	 * Breakdown xml to array of Hubwire SKU Ref ID
 	 * @param simpleXMLElement $exml,
 	 * @param sku_id_type for array key
 	 * @return object(return){success, array(result)}
 	 */
	public static function processSKU($xml, $sku_id_field = '')
	{
		try
		{
			$xml_products = $xml->xpath('//ProductStocks');
			foreach( $xml_products as $xml_product){
				$mp_skus = array();

				$arr_product = (array) $xml_product;

				$sku_id_field = ($sku_id_field == 'name') ? 'mixDtlOptNm' : 'prdStckNo';

				try
				{
					$xml_sku = $arr_product['ProductStock'];
					if(!is_array($xml_sku)){
						$xml_sku = array($xml_sku);
					}

					foreach ($xml_sku as $sku) {
						$sku = (array) $sku;
						$key = isset($sku[$sku_id_field]) ? $sku[$sku_id_field] : '';
						$mp_skus[$key] = array(
							'sku_ref_id' 	=> $sku['prdStckNo'],
							'name' 			=> isset($sku['mixDtlOptNm']) ? $sku['mixDtlOptNm'] : '',
							'add_weight'	=> isset($sku['optWght']) ? $sku['optWght'] : 0, //additional weight
							'add_price' 	=> isset($sku['addPrc']) ? $sku['addPrc'] : 0, //additional price
							'quantity'		=> $sku['stckQty'],
							'sold_quantity'	=> $sku['selQty'],
							'active'		=> ($sku['prdStckStatCd'] == '01') ? 1 : 0,
						);
					}
					$item['success'] = true;
					$item['skus'] = $mp_skus;
					$mp_product[$arr_product['prdNo']] =$item;
				}
				catch(Exception $ex)
				{
					$error = array();
					$error['error_desc'] = 'Unable to read xml,refer to input for details';
					$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
					$mp_product[$arr_product['prdNo']] = MpUtils::errorResponse($error, __METHOD__,__LINE__);
				}
			}
			return $mp_product;
		}
		catch(Exception $ex)
		{
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtl::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__,__LINE__);
		}
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
				$param['startTime'] = self::convertToElevStDateTime($param['startTime']);
				$param['endTime'] = self::convertToElevStDateTime($param['endTime']);
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

	/**
 	 * Process XML to Hubwire Sales Orders /Sales Order Items
 	 * @param $xml 11St orders response in XML
 	 * @return object(return){success, array(result)}
 	 */
	public static function processOrder($xml, $channel)
	{
		try
		{
			$orders = array();
			foreach($xml->xpath('//ns2:order') as $xml_order)
			{
				$xml_order = (array) $xml_order;
				$extra_info = json_decode($channel->channel_detail->extra_info, true);

				// order info
				$order_ref_id 		   = isset($xml_order['ordNo']) ? $xml_order['ordNo'] : 0;
				$order_date 		   = isset($xml_order['ordDt']) ? self::convertToHWDateTime($xml_order['ordDt']) : '';
				$order_status 		   = isset($xml_order['ordPrdStat']) ? $xml_order['ordPrdStat'] : '';
				$order_payment_date    = isset($xml_order['ordStlEndDt']) ? self::convertToHWDateTime($xml_order['ordStlEndDt']) : '';
				$order_confirm_date    = isset($xml_order['plcodrCnfDt']) ? self::convertToHWDateTime($xml_order['plcodrCnfDt']) : ''; // date confirmed by seller
				$order_shipping_no 	   = isset($xml_order['dlvNo']) ? $xml_order['dlvNo'] : '';
				$order_shipping_type   = isset($xml_order['dlvCstType']) ? $xml_order['dlvCstType'] : '';
				$order_shipping_remark = isset($xml_order['ordDlvReqCont']) ? $xml_order['ordDlvReqCont'] : '';
				$order_clearance_email = isset($xml_order['clearanceEmail']) ? $xml_order['clearanceEmail'] : '';

				// line item info
				$item_ref_id 			   = isset($xml_order['ordPrdSeq']) ? $xml_order['ordPrdSeq'] : 0;
				$item_ordered_qty 		   = isset($xml_order['ordQty']) ? self::removeThousandSeparator($xml_order['ordQty']) : 0;
				$item_sku_option_add_price = isset($xml_order['ordOptWonStl']) ? self::removeThousandSeparator($xml_order['ordOptWonStl']) : 0;
				$item_ordered_amount 	   = isset($xml_order['ordAmt']) ? self::removeThousandSeparator($xml_order['ordAmt']) : 0; // multiplied by qty, before any discount, inclusive of sku_option_add_price
				$item_HW_discount 		   = isset($xml_order['lstSellerDscPrc']) ? self::removeThousandSeparator($xml_order['lstSellerDscPrc']) : 0; // multiplied by qty, same as 'sellerDscPrc'
				$item_elevSt_discount	   = isset($xml_order['lstTmallDscPrc']) ? self::removeThousandSeparator($xml_order['lstTmallDscPrc']) : 0; // multiplied by qty
				$item_shipping_fee 		   = isset($xml_order['dlvCst']) ? self::removeThousandSeparator($xml_order['dlvCst']) : 0; // multiplied by qty
				$item_paid_amount 		   = isset($xml_order['ordPayAmt']) ? self::removeThousandSeparator($xml_order['ordPayAmt']) : 0; // multiplied by qty, after HW and 11St discount, inclusive of shipping fee
				$item_cancelled_qty 	   = isset($xml_order['ordCnQty']) ? self::removeThousandSeparator($xml_order['ordCnQty']) : 0;
				
				/* 	NOTE
				 	'tmallDscPrc' is the total of 'lstTmallDscPrc', which is the total 11St discount amount per order
				 	it is only shown under the first order line item, the rest of line item in the order will be having value 0
				 */ 

				// product / sku info
				$product_id 			= isset($xml_order['product_code']) ? $xml_order['product_code'] : 0;
				$product_name 			= isset($xml_order['prdNm']) ? $xml_order['prdNm'] : '';
				$product_original_price = isset($xml_order['selPrc']) ? self::removeThousandSeparator($xml_order['selPrc']) : 0; // per product
				$product_ref_id 		= isset($xml_order['prdNo']) ? $xml_order['prdNo'] : 0;
				$sku_ref_id 			= isset($xml_order['prdStckNo']) ? $xml_order['prdStckNo'] : 0;
				$sku_info 				= isset($xml_order['slctPrdOptNm']) ? $xml_order['slctPrdOptNm'] : '';
				
				// billing / shipping info
				$member_ref_id 	   = isset($xml_order['memNo']) ? $xml_order['memNo'] : 0;
				$billing_email 	   = isset($xml_order['ordEmail']) ? $xml_order['ordEmail'] : '';
				$billing_name 	   = isset($xml_order['ordNm']) ? $xml_order['ordNm'] : '';
				$billing_phone1    = isset($xml_order['ordPrtblTel']) ? $xml_order['ordPrtblTel'] : '';
				$billing_phone2    = isset($xml_order['ordTlphnNo']) ? $xml_order['ordTlphnNo'] : '';
				$billing_phone     = ($billing_phone1 != $billing_phone2) ? $billing_phone1.', '.$billing_phone2 : $billing_phone1; // combine secondary phone number if number is different
				$shipping_name 	   = isset($xml_order['rcvrNm']) ? $xml_order['rcvrNm'] : '';
				$shipping_phone    = isset($xml_order['rcvrTlphn']) ? $xml_order['rcvrTlphn'] : '';
				$shipping_address1 = isset($xml_order['rcvrDtlsAddr']) ? $xml_order['rcvrDtlsAddr'] : '';
				$shipping_address2 = isset($xml_order['rcvrBaseAddr']) ? $xml_order['rcvrBaseAddr'] : '';
				$shipping_postcode = isset($xml_order['rcvrMailNo']) ? $xml_order['rcvrMailNo'] : '';
				$shipping_mail_seq = isset($xml_order['rcvrMailNoSeq']) ? $xml_order['rcvrMailNoSeq'] : ''; // not sure what this is

				/*
				 * To find out state and city from $shipping_address2
				 * Example value of $shipping_address2: 47301 Petaling Jaya, Selangor
				*/
				$shipping_street_2 = $shipping_address2;

				// Remove $shipping_postcode with trailing space from beginning of $shipping_address2
				// Expected result string: Petaling Jaya, Selangor
				$shipping_address2 = preg_replace('/^'.$shipping_postcode.' /', '', $shipping_address2);

				// Explode $shipping_address2 by comma
				// Expected result array: ["Petaling Jaya", " Selangor"]
				$shipping_address2 = explode(',', $shipping_address2);
				$city = isset($shipping_address2[0]) ? trim($shipping_address2[0]) : '';
				$state = isset($shipping_address2[1]) ? trim($shipping_address2[1]) : '';

				if(empty($city) && empty($state)) {
					throw new Exception("Failed to capture City and State for 11Street order. Channel ID: " . $channel->id . " TP Order ID: " . $order_ref_id);
				}
				/* END */

				if(!isset($orders[$order_ref_id]))
				{
					// create new order object
					$order = new Order;

					// cumulative fields
					$order->subtotal = $item_paid_amount - $item_shipping_fee;
					$order->total = $item_paid_amount;
					$order->shipping_fee = $item_shipping_fee;
					$order->total_discount = $item_HW_discount + $item_elevSt_discount;

					//$order_statuses = Config::get('globals.order_status');
					$order->cart_discount = 0;
					$order->tp_order_id = $order_ref_id;
					$order->tp_order_code = $order_ref_id;
					$order->tp_order_date = $order_date;
					$order->tp_source = 'auto';
					$order->status = self::convertToHWOrderStatus($order_status);
					$order->paid_status = ($order->status == Order::$newStatus) ? 1 : 0;
					$order->paid_date = ($order->paid_status == 1) ? $order_payment_date : '';
					$order->cancelled_status = self::convertToHWCancelledStatus($order_status);
					$order->payment_type = 'unknown';
					$order->shipping_recipient = $shipping_name;
					$order->shipping_phone = $shipping_phone;
					$order->shipping_street_1 = $shipping_address1;
					$order->shipping_street_2 = $shipping_street_2;
					$order->shipping_postcode = $shipping_postcode;
					$order->shipping_city = $city;
					$order->shipping_state = $state;
					$order->shipping_country = '';
					$order->shipping_provider = $extra_info['shipping_provider'];
					$order->consignment_no = '';
					$order->currency = 'MYR';
        			$order->forex_rate = 1;
					$order->tp_extra = json_encode(array(
						'created_at' => $order_date,
						'shipping_no' => $order_shipping_no,
						'sale_status' => $order_status,
						'member_id' => $member_ref_id,
						'sale_shipping_type' => $order_shipping_type,
						'amount_paid' => $item_paid_amount,
						'shipping_remarks' => $order_shipping_remark,
						'clearance_email' => $order_clearance_email,
						'order_amount' => $item_ordered_amount,
						'mail_seq' => $shipping_mail_seq,
						'confirm_date' => self::convertToHWDateTime($order_confirm_date)
					));

					// create new member object
					$member = new Member;
					$member->member_name = $billing_name;
					$member->member_type = 1;
					$member->member_email = (!empty($billing_email)) ? $billing_email : $member_ref_id;
					$member->member_mobile = $billing_phone;

					$orders[$order_ref_id]['success'] = true;
					$orders[$order_ref_id]['order'] = $order;
					$orders[$order_ref_id]['member'] = $member;
				}
				else
				{
					// sum up cumulative fields
					$orders[$order_ref_id]['order']->subtotal 	 	+= $item_paid_amount - $item_shipping_fee;
					$orders[$order_ref_id]['order']->total 		  	+= $item_paid_amount;
					$orders[$order_ref_id]['order']->shipping_fee   += $item_shipping_fee;
					$orders[$order_ref_id]['order']->total_discount += $item_HW_discount + $item_elevSt_discount;
				}

				// create new order item object
				for ($i=0; $i<$item_ordered_qty; $i++) {
					$item = new OrderItem;
					$item->ref_type = 'ChannelSKU';
					$item->sold_price = ($item_ordered_qty > 0) ? round(($item_paid_amount-$item_shipping_fee)/$item_ordered_qty, 2) : 0;
					$item->tax_inclusive = 1;
					$item->tax_rate = 0.06;
					$item->tax = round($item->sold_price/1.06*0.06, 2);
					$item->quantity = $item->original_quantity = 1;
					$item->discount = ($item_ordered_qty > 0) ? round($item_HW_discount/$item_ordered_qty, 2) : 0;
					$item->tp_discount = ($item_ordered_qty > 0) ? round($item_elevSt_discount/$item_ordered_qty, 2) : 0;
					$item->weighted_cart_discount = 0;
					$item->tp_item_id = $item_ref_id;
					$item->channel_sku_ref_id = $sku_ref_id;
					$item->product_name = $product_name;

					$orders[$order_ref_id]['items'][] = $item;
				}
			}

			return $orders;
		}
		catch(Exception $e)
		{
			$error = array();
			$error['error_desc'] = $e->getMessage();
			$error['status_code'] = MpUtils::getStatusCode('SERVER_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
	}

	/**
 	 * Breakdown xml to array of Categories
 	 * @param int $type
 	 * @param object $param
 	 * @return object(return){success, array(result)}
 	 */
	public static function processCategories($xml)
	{
		try
		{
			$categories = array();

			foreach($xml->xpath('//ns2:category') as $category) {
				$category = (array) $category;
				$categories[$category['dispNo']] = array(
					'depth' 	  => $category['depth'],
					'displayName' => $category['dispNm'],
					'displayEn'   => $category['dispEngNm'],
					'parentID' 	  => $category['parentDispNo'],
				);
			}

			// Sort
	        $sorted = array();
	        foreach($categories as $k=>$v)
	        {
	            $tmp = (object) $v;
	            $tmp->id = $k;
	            $sorted[] = $tmp;
	        }

			for ($i=0; $i<count($sorted); $i++)
			{
				for ($j=$i+1; $j<count($sorted); $j++)
				{
					if($sorted[$i]->depth > $sorted[$j]->depth)
					{
	                    //swap
						$tmp = $sorted[$i];
						$sorted[$i] = $sorted[$j];
						$sorted[$j] = $tmp;
					}
				}
			}

			$result = array();
			for($i=count($sorted)-1; $i>-1; $i--)
			{
				$sorted[$i]->path = substr(self::findCategoryRoot($sorted[$i]->parentID, $sorted, 0, count($sorted)-1) ."/". ucwords(trim($sorted[$i]->displayName)), 1);
				$result[$sorted[$i]->id] = $sorted[$i];
			}
			foreach($result as $obj)
			{
				unset($result[$obj->parentID]);
			}

	        //extract id and name
			$dropdownList = array();
			foreach ($result as $cat_id=>$category) 
			{
				$dropdownList[$cat_id] = $category->path;
			}

			$return['count'] = sizeof($dropdownList);
			$return['categories'] = $dropdownList;
			$return['success'] = true;
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

	/* findCategoryRoot for categories */
	private static function findCategoryRoot($pid, $data, $i, $n)
	{
		$text = "";
		if($pid == 0)
			return "";

		while($n >= $i)
		{
			if($pid == $data[$i]->id)
			{
				$text .= ucwords(trim(self::findCategoryRoot($data[$i]->parentID, $data, 0, $n))) ."/". ucwords(trim($data[$i]->displayName));
				return $text;
			} else {
				$i++;
			}
		}
	}

	/**
 	 * 11St order statuses
	 * 101 : Order Complete
	 * 102 : Awaiting Payment
	 * 103 : Awaiting Pre-order
	 * 201 : Pre-order Payment Complete
	 * 202 : Payment Complete
	 * 301 : Preparing for Shipment
	 * 401 : Shipping in Progress
	 * 501 : Shipping Complete
	 * 601 : Return Requested
	 * 701 : Cancellation Requested
	 * 801 : Awaiting for Re-approval
	 * 901 : Purchase Confirmed
	 * A01 : Return Complete
	 * B01 : Order Cancelled
	 * C01 : Cancel Order upon Purchase Confirmation
 	 */

	private static function convertToHWOrderStatus($elevSt_order_status)
	{
		switch($elevSt_order_status){
			case '101': //'Order Complete'
        	case '102': //'Awaiting Payment'
        	case '103': //'Awaiting Pre-order'
	        case '801': // 'Awaiting for Re-approval'
        		$HW_order_status = Order::$pendingStatus; // pending
        		break;
			default:
				$HW_order_status = Order::$newStatus; //new
				break;
		}
		return $HW_order_status;
	}

	/**
	 * @return $cancelled_status boolean 
	 */
	private static function convertToHWCancelledStatus($elevSt_order_status)
	{
		switch($elevSt_order_status){
			case '601': // 'Claim Requested'
	        case '701': // 'Cancellation Requested'
	        case 'A01': // 'Return Complete'
	        case 'B01': // 'Order Cancelled'
	        case 'C01': // 'Cancel Order upon Purchase Confirmation'
				$cancelled_status = 1; // cancelled
				break;
	        default:
				$cancelled_status = 0; // not cancelled
				break;
		}
		return $cancelled_status;
	}

	/**
 	 * Convert 11St datetime to HW datetime designated for marketplace orders
 	 * @param $elevenSt_date
 	 * @return $HW_date
 	 */
	public static function convertToHWDateTime($elevenSt_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$elevenSt_timezone = Config::get('elevenSt.marketplace_timezone');
		$HW_date_format = Config::get('marketplace.std_date_format');

		return DateTimeUtils::convertTime($elevenSt_date, $HW_date_format, $elevenSt_timezone, $HW_timezone);
	}

	/**
 	 * Convert HW datetime to 11St datetime
 	 * @param $HW_date
 	 * @return $elevenSt_date
 	 */
	public static function convertToElevStDateTime($HW_date) {
		$HW_timezone = Config::get('marketplace.default_timezone');
		$elevenSt_timezone = Config::get('elevenSt.marketplace_timezone');
		$elevenSt_date_format = Config::get('elevenSt.queryStr_date_format');

		return DateTimeUtils::convertTime($HW_date, $elevenSt_date_format, $HW_timezone, $elevenSt_timezone);
	}

	/**
 	 *
 	 * @param string $type
 	 * @param object $queryString
 	 * @return object(return){success, array(error)}
 	 */
	public static function verifyOrderRequest($type, $queryString)
	{
		$messages = array();
		if($type == MpUtils::getSalesStatus('Packing'))
		{
			$rules = array(
				'order_ref_id' => 'required',
			);
		} 
		elseif($type == MpUtils::getSalesStatus('Shipped'))
		{
			$i_date_format = MpUtils::getDateFormat();
			$rules = array(
				'sent_date' 	  => 'required|date_format:'.$i_date_format,
				'shipping_method' => 'required|in:01',
				'courier_company' => 'required_if:shipping_method,01',
				'tracking_no'	  => 'required_if:shipping_method,01',
				'shipping_no'	  => 'required',
			);
		}
		elseif($type == MpUtils::getSalesStatus('Cancelled'))
		{
			$i_date_format = MpUtils::getDateFormat();
			$rules = array(
				'order_ref_id' 	 	=> 'required',
				'order_item_ref_id' => 'required',
				'cancel_reason' 	=> 'required|in:06,07,08,09,10,99',
				'remarks'			=> 'required',
			);
		}

		$validator = Validator::make($queryString, $rules, $messages);
		if ($validator->fails())
		{
			$error = array();
			$error['error_desc'] = json_encode($validator->messages()->toArray());
			$error['status_code'] = MpUtils::getStatusCode('VALIDATION_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
		
		return array('success'=>true);
	}

	/**
 	 * Verify order request filters and convert to correct datetime format
 	 * @param int $type
 	 * @param object $param
 	 * @return object(return){success, array(result)}
 	 */
	public static function prepareOrderStatusRequest($status, $details)
	{
		$configStatus = MpUtils::getSalesStatus();
		
		switch($status)
		{
			case $configStatus['Packing']:
				if(!isset($details['order_item_ref_id'])) $details['order_item_ref_id'] = '1';
				$queryStr = "{$details['order_ref_id']}/{$details['order_item_ref_id']}";
				break;
			case $configStatus['Shipped']:
				$details['sent_date'] = self::convertToElevStDateTime($details['sent_date']);
				$queryStr = "{$details['sent_date']}/{$details['shipping_method']}/{$details['courier_company']}/{$details['tracking_no']}/{$details['shipping_no']}";
				break;
			case $configStatus['Cancelled']:
				$details['remarks'] = urlencode($details['remarks']);
				$queryStr = "{$details['order_ref_id']}/{$details['order_item_ref_id']}/{$details['cancel_reason']}/{$details['remarks']}";
				break;
		}

		$return = array();
		$return['success']  = true;
		$return['queryStr'] = $queryStr;
		return $return;
	}

	public static function removeThousandSeparator($value){
		return str_replace(',', '',$value);
	}
}