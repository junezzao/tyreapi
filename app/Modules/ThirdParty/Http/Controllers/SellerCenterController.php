<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Controllers\Admin\AdminController;
use GuzzleHttp\Exception\RequestException as RequestException;
use GuzzleHttp\Client as Guzzle;

use App\Repositories\CustomFieldsRepository;
use App\Repositories\SyncRepositories;
use App\Repositories\Eloquent\SyncRepository;
use App\Modules\ThirdParty\Repositories\SellerCenterRepo;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Config;

use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Document;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;

use Monolog;
use DateTime;
use Carbon\Carbon;
use SimpleXMLElement;
use Exception;
use Response;

/**
 * The SellerCenterController class contains various method for us to
 * interact with Seller Center API.
 *
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */

class SellerCenterController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $__api, $customLog, $sync, $order;
	private $error_data = array(), $bulkSync = array();
	private $livePriceSkus = array();

	public function exceptionHandler($e) {
        $class = explode('\\', get_class($this));
		$class_name = $class[count($class)-1];
		$error['subject'] = 'Error '. $class_name;
        $error['File'] = __FILE__;
		$error['Line'] = $e->getLine();
		$error['ErrorDescription'] = $e->getMessage();
		$this->error_data = $error + $this->error_data;

		Log::info(__CLASS__. '(' . $e->getLine() . ') >> Error: ' . $e->getMessage());
	}

	public function errorHandler($function, $line, $response, $message = '') {
		if(!empty($response)) {
			$this->error_data['response'] = $response;
		}

        $class = explode('\\', get_class($this));
		$class_name = $class[count($class)-1];
		$error['subject'] = 'Error '. $class_name;
        $error['File'] = __FILE__;
		$error['Function'] = $function;
		$error['Line'] = $line;
		$error['ErrorDescription'] = $message;
		$this->error_data = $error + $this->error_data;

		if(!empty($this->sync)) {
			$this->sync->status = 'FAILED';
			$this->sync->remarks = $message;
			$this->sync->save();
			$this->error_data['Sync'] = $this->sync->toArray();
		}else{
			foreach($this->bulkSync as $sync) {
				$sync->status = 'FAILED';
				$sync->remarks = $message;
				$sync->save();
				ThirdPartySync::updateSyncStatus($sync);
			}
			$this->error_data['Sync Type'] = 'Bulk Sync';
			$this->error_data['Sync IDs'] = implode(', ', array_keys($this->bulkSync));
		}

		$this->ErrorAlert($this->error_data);
	}

	public function initialize($channel, ThirdPartySync $sync = null)
	{
		$this->__setApi($channel);
		$this->sync = is_null($sync) ? null : $sync;
		return $this;
	}

	public function setOrder($order)
	{
		$this->order = $order;
	}

	private function array_to_xml($array_info, &$xml_info) {
		foreach($array_info as $key => $value) {
			if(is_array($value)) {
	        	// check the children
				for (reset($value); is_int(key($value)); next($value));
					$onlyIntKeys = is_null(key($value));
				if($onlyIntKeys){
					foreach($value as $values){
						$subnode = $xml_info->addChild("$key");
						$this->array_to_xml($values, $subnode);
					}
				}
				else {
					$subnode = $xml_info->addChild("$key");
					$this->array_to_xml($value, $subnode);
				}
			}
			else {
				if(strpos($key, "Image") !== false){
					$xml_info->addChild("Image",htmlspecialchars("$value"));
				}
				else{
					$xml_info->addChild("$key",htmlspecialchars("$value"));
				}
			}
		}
	}

	private function xml2array($xml)
	{
		$arr = array();
		$json = json_encode((array)($xml));
		$arr = json_decode($json, TRUE);
		array_walk($arr, array($this,'check_array'));
		return $arr;
	}

	public function check_array(&$item, $key){
		if(is_array($item)){
	    	if(!empty($item))
	    		array_walk($item, array($this,'check_array'));
	    	else
	    		$item = '';
		 }
	}

	public function api($channel = null){
		if(!empty($channel)){
			$this->__setApi($channel);
		}
		return $this->__api;
	}

	private function __setApi($channel){


		$this->channel = $channel;
		$this->__api = function($method, $url_params = array() , $params = array(), $sync_id = null) use ($channel)
		{
			try{
				$url = '';
				$client = new Guzzle(array('request.options'=>array('headers'=>array('Content-Type'=>'application/x-www-form-urlencoded'))));

				$logInfo = "";

				if (!is_null($this->bulkSync)) {
					foreach($this->bulkSync as $sync) {
						$logInfo .= !is_null($sync) ? ("Sync ID " . $sync->id . ", Event: " . $sync->action . " | ") : "";
					}
				}
				else {
					$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
				}

				$logInfo .= "Request Body/Query: ";
				$this->customLog->addInfo($logInfo, $params);

				$now = new DateTime();
				$url = $channel->channel_detail->api_secret.'?';
				$url_params['UserID'] = $channel->channel_detail->api_password;
				$url_params['Version'] = '1.0';
				$url_params['Timestamp'] = $now->format(DateTime::ISO8601);
				ksort($url_params);
				$parameters = array();
				foreach($url_params as $name => $value){
					$parameters[] = rawurlencode($name) . '=' . rawurlencode($value);
				}
				$strtosign = implode('&',$parameters);
				$url_params['Signature'] = rawurlencode(hash_hmac('sha256', $strtosign, $channel->channel_detail->api_key, false));

				$url .= http_build_query($url_params, '', '&', PHP_QUERY_RFC3986);

				$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Request></Request>");

				// build xml payload
				$this->array_to_xml($params,$xml);
				$xml_str = $xml->asXML();
				$this->error_data['url'] = $url;
				$this->error_data['xml_body'] = $xml_str;
				\Log::info('method...' .print_r($method, true));
				\Log::info('url...' .print_r($url, true));
				\Log::info('xml_str...' .print_r($xml_str, true));
				die();
				$response = $client->request($method, $url, array('body'=>$xml_str));
			}
			catch (RequestException $e){
				$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;
				$response = $e->getResponse();

				$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';
				$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

				if (!empty($response)) {
					$this->customLog->addError(json_encode($response));

					$errorCode = $response->getStatusCode();
					if ($errorCode == 429 && !empty($this->sync)) {
						$this->sync->status = 'RETRY';
						$this->sync->remarks = 'Seller Center Error: E429 Too many requests';
						$this->sync->save();
					}
				}

				return false;
			}
			catch(Exception $e){
				$message = "Error send request. Unknown error. Request URL: " . $url;
				$message .= ' | Error: ' . $e->getMessage();

			    $this->errorHandler(__FUNCTION__, $e->getLine(), (!empty($response) ? $response : null), $message);
				return false;
			}

			$response = simplexml_load_string($response->getBody()->getContents());
			$response = $this->xml2array($response);
			$response['success'] = true;

			$error_desc = array();
			if(!empty($response['Head']['ErrorMessage']))
			{
				if(!empty($response['Head']['ErrorMessage']))
				{
					$error_desc[] = $response['Head']['ErrorMessage'];
					if(!empty($response['Body']['ErrorDetail'])) {
						if(!empty($response['Body']['ErrorDetail'][0]) && is_array($response['Body']['ErrorDetail'][0])) {
							foreach($response['Body']['ErrorDetail'] as $detail){
								$error_desc[] = $detail['Message'];
							}
						}
						else{
							$error_desc[] = $response['Body']['ErrorDetail']['Message'];
						}
					}
				}
			}

			if(!empty($error_desc)) {
				$message = "Seller Center Error: " . json_encode($error_desc);
				$message .= " | Request URL: " . $url;
				$this->error_data['response'] = $response;
				$this->error_data['message'] = json_encode($error_desc);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);

				// if ($url_params['Action'] == 'FeedStatus') {
				// 	$response['success'] = false;
				// 	return $response;
				// }

				return false;
			}
			return $response;
		};
	}

	public function getOrders(array $filter)
	{
		$params = array();
		$params['Action'] = 'GetOrders';

		$response = SellerCenterRepo::prepareOrderRequest(MpUtils::getTypeCode('GET_ORDER'), $filter);
		if(!$response['success'])
		{
			return $response;
		}

		$params['CreatedAfter'] = $response['param']['startTime'];
		$params['CreatedBefore'] = $response['param']['endTime'];//new add code

		$sellerCenter = $this->api($this->channel);
		$response = $sellerCenter('GET', $params, array());

		if(!$response['success']) {
			return $response;
		}

		$orders = array();

		if(!isset($response['Body'])){
			$error = array();
			$error['error_desc'] = "Response Body Not Found.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
		elseif(empty($response['Body']['Orders']['Order']))
		{
			$error = array();
			$error['error_desc'] = "Order is empty.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}

		$tmps = array();
		$tmpVal = array_values($response['Body']['Orders']['Order']);
		if(is_array($tmpVal[0])) // more than 1 order items
			$tmps = $response['Body']['Orders']['Order'];
		else
			$tmps[] = $response['Body']['Orders']['Order'];

		foreach($tmps as $tmp){
			$orders[$tmp['OrderId']] = $tmp;
		}

		$response2 = $this->getMultipleOrderItems($orders, $this->channel);
		if(!$response2['success']) {
			return $response2; //failed to get Multiple order items.
		}

		$channel = Channel::where('id', $this->channel->id)->first();

		return SellerCenterRepo::processOrder($orders, $channel);
	}

	public function getDocumentDemand($item_id,$channel_id, $type){
		$channel = Channel::with('channel_detail')->find($channel_id);
		$item = OrderItem::findOrFail($item_id);

		if (in_array($item->status, ['Picked', 'Verified']) && $item->quantity > 0) {
			// do nothing
		} else {
			$response = [
				'success' => false,
				'channel' => $this->channel
			];
			return Response::JSON($response, MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		$document_types = array('invoice','shippingLabel');
		$params['Action'] = 'GetDocument';
		$params['OrderItemId'] = $item->tp_item_id;
		$params['DocumentType'] = $type;

		$data = array();
		$sellercenter = $this->api($channel);
		$this->error_data['handler']['params'] = $params;
		$this->error_data['handler']['data'] = $data;

		$response = $sellercenter('GET', $params, $data);

		$this->error_data['handler']['response'] = $response;
		if(!$response['success']){
			$response['channel'] = $this->channel;
			$this->customLog->addError(__FUNCTION__.' >> Throwing exception.');
			return Response::JSON($response, MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		$file = base64_decode($response['Body']['Documents']['Document']['File']);
		$document = Document::firstOrNew(
						array(
							'document_type'=>$params['DocumentType'],
							'item_id'=>$item->id,
							'sale_id'=>$item->order_id
						)
					);
		$document->document_content = $file;
		$document->save();

		return $response;
	}

	public function getSingleOrder($order_code) {}

	public function getMultipleOrderItems(&$orders, $channel) {
		$order_ids = array_keys($orders);

		$params['Action'] = 'GetMultipleOrderItems';
		$params['OrderIdList'] = '['.implode(',',$order_ids).']';

		$sellerCenter = $this->api($channel);
		$response = $sellerCenter('GET', $params, array());
		if(!$response['success']){
			return $response;
		}

		if(!isset($response['Body'])){
			$error = array();
			$error['error_desc'] = "Response Body Not Found.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}

		$tmps = array();
		$tmpVal = array_values($response['Body']['Orders']['Order']);
		if(is_array($tmpVal[0])) // more than 1 order items
			$tmps = $response['Body']['Orders']['Order'];
		else
			$tmps[] = $response['Body']['Orders']['Order'];

		foreach($tmps as $order){
			if(empty($order['OrderItems']['OrderItem'])) continue;
			$tmpVal = array_values($order['OrderItems']['OrderItem']);
			if(is_array($tmpVal[0])) // more than 1 order items
				$orders[$order['OrderId']]['OrderItems'] = $order['OrderItems']['OrderItem'];
			else
				$orders[$order['OrderId']]['OrderItems'][] = $order['OrderItems']['OrderItem'];
		}

		return array('success' => true);
	}

	/**
	 * Get Categories
	 * @return array $return
	 */
	public function getCategories()
	{
		$url_params = array(
            'Action' => 'GetCategoryTree',
            'Format' => 'JSON',
        );

        $channel = $this->channel->channel_detail;
        $url = $channel->api_secret.'?'.SellerCenterRepo::generateUriString($url_params, $channel->api_password, $channel->api_key);

        $response = SellerCenterRepo::guzzleRequest('GET', $url);
        if(!$response['success'])
        {
            return $response;
        }
        $response = SellerCenterRepo::processCategories($response['body'], array('endLevel'));
        return $response;
	}

	private function checkOrderItemsForRTS($tpOrderId, array $tpItemIds) {
		$items = $this->getOrderItems($this->channel, $tpOrderId);

		foreach ($items as $item) {
			if (in_array($item['OrderItemId'], $tpItemIds) && strcasecmp($item['Status'], 'pending') != 0 && strcasecmp($item['Status'], 'ready_to_ship') != 0) {
				return ['success' => false, 'error' => 'All ready-to-ship items must have status "Pending" or "Ready To Ship" in Zalora.'];
			}
		}

		return ['success' => true];
	}

	public function readyToShip($input)
	{
		try
		{
			$response = array();
			$this->error_data['handler']['input'] = $input;

			$refs = array();
			foreach($this->order->items as $item)
			{
				if (in_array($item->status, ['Picked', 'Verified']) && $item->quantity > 0)
					$refs[] = $item->tp_item_id;
			}

			if (count($refs) == 0) {
				return ['success' => false, 'message' => 'No items to ship.'];
			}

			$data = array();
			$params['Action'] 			  = 'SetStatusToReadyToShip';
			$params['OrderItemIds'] 	  = '['. implode(',', $refs) .']';
			$params['DeliveryType'] 	  = Config::get('sellerCenter.default.DeliveryType');
			// $params['DeliveryType'] 	  = 'pickup'; // uncomment this line when testing on zalora staging order

			$check = $this->checkOrderItemsForRTS($this->order->tp_order_id, $refs);

			if (!$check['success']) {
				return ['success' => false, 'message' => $check['error']];
			}

			$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
			$params['ShippingProvider']   = ($this->order->payment_type == 'CashOnDelivery') ? $extra_info['shipping_provider_cod'] : $extra_info['shipping_provider'];
			if(!empty($input['tracking_no'])) $params['TrackingNumber'] = $input['tracking_no'];

			$this->error_data['handler']['params'] = $params;
			$this->error_data['handler']['data'] = $data;

			$sellerCenter = $this->api($this->channel);
			$this->customLog->addInfo('ReadyToShip sending Guzzle request... '. print_r($params, true));
			$response = $sellerCenter('POST', $params, $data);
			$this->customLog->addInfo('ReadyToShip response... '. print_r($response, true));
			$this->error_data['handler']['response'] = $response;

			$response2 = $this->getConsignmentNumber($this->channel, $this->order->tp_order_id);

			if(isset($response2['success']) && !$response2['success']) {
				$error = $response2['message'];
				$error .= ((isset($response['success']) && !$response['success']) || !isset($response['Head'])) ? (' | ' . $response['error_desc']) : '';

				throw new Exception($error);
			}

			return $response2;
		}
		catch(Exception $e)
		{
			$message = 'Error: '. $e->getMessage() .' in '. $e->getFile() .' at line: '. $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
			return array('success'=>false, 'message' => (!empty($this->error_data['message'])) ? $this->error_data['message'] : $this->error_data['ErrorDescription']);
		}
	}

	public function getConsignmentNumber($channel, $tp_order_id)
	{
		$orderItems = $this->getOrderItems($channel, $tp_order_id);
		if(isset($orderItems['success']) && !$orderItems['success'])
		{
			return $orderItems;
		}

		$trackingCodes = array();
		foreach($orderItems as $orderItem)
		{
			if($orderItem['TrackingCode'] != '')
				$trackingCodes[] = $orderItem['TrackingCode'];
		}

		if (count($trackingCodes) == 0) {
			return array('success' => false, 'message' => 'No tracking codes found.');
		}

		//Remove duplicate tracking codes
		$trackingCodes = array_unique($trackingCodes);

		$response['success'] = true;
		$response['tracking_no'] = implode(', ', $trackingCodes);
		return $response;
	}

	public function getOrderItems($channel, $tp_order_id)
	{
		$params['Action'] = 'GetOrderItems';
		$params['OrderId'] = $tp_order_id;

		$data = array();
		$sellerCenter = $this->api($channel);
		$response = $sellerCenter('GET', $params, $data);
		if(isset($response['success']) && !$response['success']){
			$response['channel'] = $this->channel;
			$this->customLog->addError(__FUNCTION__ . ' >> Throwing exception.');
			return array('success' => false, 'message' => $response['error_desc']);
		}

		if(!isset($response['Body']['OrderItems']['OrderItem'])) {
                	\Log::info('Seller Center getOrderItems response... '.print_r($response, true));
			return array('success'=>false, 'message'=>'Something went wrong with Seller Center API, please try again later.');
                }

		$tmpVal = array_values($response['Body']['OrderItems']['OrderItem']);
		if(is_array($tmpVal[0])) // more than 1 order items
			return $response['Body']['OrderItems']['OrderItem'];
		else
			return array($response['Body']['OrderItems']['OrderItem']);
	}

	public function setSalesStatus($order_id, array $details) {}

    public function sendResponse($response) {}

    public function createProduct(array $product, $bulk = false)
    {
    	$data = $this->prepareProduct($product);
    	$this->livePriceSkus = $data['livePriceSkus'];

        return $this->pushProduct($data, 'Create');
    }

    public function updateProduct(array $product, $bulk = false)
    {
		$data = $this->prepareProduct($product, false);
		$this->livePriceSkus = $data['livePriceSkus'];
		
    	return $this->pushProduct($data,'Update');
    }

    private function pushProduct($data, $action = 'Create', $isBulk = false)
    {
        try
        {
            if(!$isBulk && !$data['success'])
            {
                return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }elseif($isBulk && !isset($data['product'])){
            	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

            $response = array();
            $request = $this->api();
            $params['Action'] = 'Product' . $action;

            if($isBulk){
            	$response = $request('POST', $params, $data['product'], null);
            }else{
            	$response = $request('POST', $params, $data['product'], $this->sync->sync_id);
            }

            $this->error_data['handler']['response'] = $response;

            if(!$response['success']){
                return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

            if($isBulk){
            	foreach($this->bulkSync as $syncId => $sync) {
	            	$sync->request_id = $response['Head']['RequestId'];
		            $sync->remarks = json_encode($response);
		            $sync->status = 'SENT';
		            $sync->save();
		            ThirdPartySync::updateSyncStatus($sync);
            	}
            }else{
            	$this->sync->request_id = $response['Head']['RequestId'];
	            $this->sync->remarks = json_encode($response);
	            $this->sync->status = 'SENT';
	            $this->sync->save();
            }
            
            ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
        }
        catch(Exception $e)
        {
            $message = 'Error: '. $e->getMessage().' at line: '.$e->getLine();
            $this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
            return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
        }
    }

    public function updateVisibility(array $product, $bulk = false) {}

    public function deleteProduct(array $product, $bulk = false) {}

    public function createSku(array $sku, $bulk = false) {
    	if($bulk){
    		$failedSyncs = array();
    		$parentSku = array();
			foreach($sku['syncData'] as $index => $syncData){
				// if sync already failed, just skip it
				if(!in_array($syncData['sync']->id, $failedSyncs)){
		    		// overwrite ['productData']
		    		$this->sync = $syncData['sync'];
		    		$productData = $this->prepareProduct($syncData['productData'], true);

		    		if(!$productData['success']){
		    			$failedSyncs[] = $syncData['sync']->id;
		    			unset($this->bulkSync[$syncData['sync']->id]);
		    			continue;
		    		}else{
		    			// to set parent sku for create products action
			    		if($syncData['sync']->action == 'createProduct'){
			    			if(!isset($parentSku[$syncData['sync']->id])){
			    				$parentSku[$syncData['sync']->id] = $productData['product']['Product'][0]['SellerSku'];
			    			}else{
			    				$productData['product']['Product'][0]['ParentSku'] = $parentSku[$syncData['sync']->id];
			    			}
			    		}

		    			$sku['product']['Product'][] = $productData['product']['Product'][0];
		    			$this->livePriceSkus += $productData['livePriceSkus'];
		    		}
		    		unset($this->sync);
		    	}
	    	}
	    	return $this->pushProduct($sku, 'Create', true);
    	}else{
	    	$data = $this->prepareProduct($sku, true);
	    	$this->livePriceSkus = $data['livePriceSkus'];

        	return $this->pushProduct($data, 'Create');
    	}
    }

    public function updateSku(array $sku, $bulk = false) {
    	if($bulk){
    		$failedSyncs = array();
    		$parentSku = array();
			foreach($sku['syncData'] as $index => $syncData){
				// if sync already failed, just skip it
				if(!in_array($syncData['sync']->id, $failedSyncs)){
					$this->sync = $syncData['sync'];

		    		$productData = $this->prepareProduct($syncData['productData'], false);

		    		if(!$productData['success']){
		    			$failedSyncs[] = $syncData['sync']->id;
		    			unset($this->bulkSync[$syncData['sync']->id]);
		    			continue;
		    		}else{
		    			// to set parent sku for create products action
			    		if($syncData['sync']->action == 'updateProduct'){
			    			if(!isset($parentSku[$syncData['sync']->id])){
			    				$parentSku[$syncData['sync']->id] = $productData['product']['Product'][0]['SellerSku'];
			    			}else{
			    				$productData['product']['Product'][0]['ParentSku'] = $parentSku[$syncData['sync']->id];
			    			}
			    		}

		    			$sku['product']['Product'][] = $productData['product']['Product'][0];
		    			$this->livePriceSkus += $productData['livePriceSkus'];
		    		}
		    		unset($this->sync);
		    	}
	    	}
	    	return $this->pushProduct($sku, 'Update', true);
    	}else{
	    	$data = $this->prepareProduct($sku, false);
	    	$this->livePriceSkus = $data['livePriceSkus'];

        	return $this->pushProduct($data, 'Update');
    	}
    }

    public function updateQuantity(array $sku, $bulk = false) {
    	if($bulk){
    		$failedSyncs = array();
    		$parentSku = array();
			foreach($sku['syncData'] as $index => $syncData){
				// if sync already failed, just skip it
				if(!in_array($syncData['sync']->id, $failedSyncs)){
					$this->sync = $syncData['sync'];
		    		$productData = $this->prepareQuantity($syncData['productData']);

		    		if(!$productData['success']){
		    			$failedSyncs[] = $syncData['sync']->id;
		    			unset($this->bulkSync[$syncData['sync']->id]);
		    			continue;
		    		}else{
		    			$sku['product']['Product'][] = $productData['product']['Product'][0];
		    		}
		    		unset($this->sync);
		    	}
	    	}
	    	return $this->pushProduct($sku, 'Update', true);
    	}else{
	        $data = $this->prepareQuantity($sku);
	        return $this->pushProduct($data, 'Update');
	    }
    }

    public function updatePrice(array $sku, $bulk = false) {
    	if($bulk){
    		$failedSyncs = array();
    		$parentSku = array();
			foreach($sku['syncData'] as $index => $syncData){
				// if sync already failed, just skip it
				if(!in_array($syncData['sync']->id, $failedSyncs)){
					$this->sync = $syncData['sync'];
		    		$productData = $this->preparePrice($syncData['productData']);

		    		if(!$productData['success']){
		    			$failedSyncs[] = $syncData['sync']->id;
		    			unset($this->bulkSync[$syncData['sync']->id]);
		    			continue;
		    		}else{
		    			$sku['product']['Product'][] = $productData['product']['Product'][0];
		    			$this->livePriceSkus += $productData['livePriceSkus'];
		    		}
		    		unset($this->sync);
		    	}
	    	}
	    	return $this->pushProduct($sku, 'Update', true);
    	}else{
	        $data = $this->preparePrice($sku);
	        $this->livePriceSkus = $data['livePriceSkus'];

	        return $this->pushProduct($data, 'Update');
	    }
    }

    public function deleteSku(array $sku, $bulk = false) {}

    public function updateImages(array $product, $isBulk = false) {

        try {
            $response = array();
            $request = $this->api();
            $dimensions = Config::get('marketplace.image_size.xl');
            $responses = array();
            if($isBulk){
            	$data = array();
            	foreach($product['syncData'] as $productData){
            		$tempData = array();
            		$tempData['SellerSku'] = $productData['productData']['parent'];

		            $imgNum = 1;
		            foreach ($productData['productData']['images'] as $order => $image) {
		                    $tempData['Images']['Image' . $imgNum] = $image['path'] . '_' . $dimensions['width'] . 'x' . $dimensions['height'];
		                    $imgNum++;
		            }
		            $data['ProductImage'][] = $tempData;
            	}
            }else{
            	$data['ProductImage'] = array(
                    'SellerSku' => $product['parent']
                );

	            $imgNum = 1;
	            foreach ($product['images'] as $order => $image) {
	                    $data['ProductImage']['Images']['Image' . $imgNum] = $image['path'] . '_' . $dimensions['width'] . 'x' . $dimensions['height'];
	                    $imgNum++;
	            }
            }
            $params['Action'] = 'Image';
            $request = $this->api();

            if($isBulk)
            	$response = $request('POST', $params, $data, null);
            else
            	$response = $request('POST', $params, $data, $this->sync->sync_id);

            $this->error_data['handler']['response'] = $response;

            if(!$response['success']){
                return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }

            if($isBulk){
            	foreach($this->bulkSync as $syncId => $sync) {
	            	$sync->request_id = $response['Head']['RequestId'];
		            $sync->remarks = json_encode($response);
		            $sync->status = 'SENT';
		            $sync->save();
		            ThirdPartySync::updateSyncStatus($sync);
            	}
            }else{
            	$this->sync->request_id = $response['Head']['RequestId'];
	            $this->sync->remarks = json_encode($response);
	            $this->sync->status = 'SENT';
	            $this->sync->save();
            }
        }
        catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage() . ' at line: ' . $e->getLine();
            $this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
        }

    }

    public function prepareQuantity(array $product)
    {
    	foreach($product['sku_in_channel'] as $channel_sku_id => $channel_sku)
    	{
	    	$temp_qty = $channel_sku['quantity'];
	    	$data_product = array(
					'SellerSku'	=> $channel_sku['hubwire_sku'],
					'Quantity'	=> $temp_qty,
					);

			// check if channel_sku has sold quantity
			$reserved_qty = ReservedQuantity::where('channel_sku_id','=',$channel_sku_id)
								->first();

			if(!empty($reserved_qty)) {
				$temp_qty += $reserved_qty->quantity;
			}

			$data_product['Quantity'] = $temp_qty;

			$data_product['Quantity'] = ($data_product['Quantity'] < 0) ? 0 : $data_product['Quantity'];
			$data['Product'][] = $data_product;
		}
        return ['success'=>true, 'product'=>$data];
    }

    public function getProductBySellerSku($hubwireSku) {
    	$response = array();

		$request = $this->api();

		$params['Action'] = 'GetProducts';
		$params['SkuSellerList'] = '["' . $hubwireSku . '"]';

		$response = $request('GET', $params, array());
		$this->error_data['handler']['response'] = $response;

		if($response === false){
			return false;
		}

		if (!empty($response['Body']['Products']) && count($response['Body']['Products']) > 0) {
			return true;
		}
		else {
			return false;
		}
    }

    public function bulkFeedStatus($bulkSync)
    {
    	$params['Action'] = 'FeedStatus';
    	$params['FeedID'] = $bulkSync['feedId'];
    	$channel = Channel::with('channel_detail')->find($bulkSync['channelId']);
    	$request = $this->api($channel);
    	$data = array();
		$feedErrors = array();
		$feedWarnings = array();
    	$this->error_data['handler']['data'] = $data;

    	foreach($bulkSync['syncs'] as $sync) {
    		$this->bulkSync[] = $sync;
    	}

    	$response = $request('GET', $params, $data);

    	if(empty($response['Body']['FeedDetail']))
        {
            //if server error do nothing and try again later
            return;
        }

    	// errors handling for feed status has been moved out from _api to here
    	/*
	        If its Feed Checks on 3 condition
	        1.  For FeedError if there is any
	        2.  Failed Records
	        3.  Status : If there are any other status other than FINISHED / SUCCESS e.g. ERROR
	    */
        $status = strtoupper($response['Body']['FeedDetail']['Status']);

        // if feed status is still in queued or processing, ignore it
        if($status == 'QUEUED' || $status == 'PROCESSING'){
        	return;
        }

    	// goes thru warning response and determine which sync has warnings based on seller SKU
    	if(!empty($response['Body']['FeedDetail']['FeedWarnings']['Warning']) && count($response['Body']['FeedDetail']['FeedWarnings']['Warning']) > 0) {
    		// loop thru warnings and set sellerSKU as array index to be used to determine which sync it belongs to.
			$warningList = $response['Body']['FeedDetail']['FeedWarnings']['Warning'];
			if (isset($warningList['SellerSku'])) $warningList = array($warningList);

			foreach($warningList as $warning) {
				$feedWarnings[$warning['SellerSku']][] = $warning['Message'];
    		}
    	}

    	// check for error message
        if (!($response['Body']['FeedDetail']['FailedRecords'] == 0 && ($status == 'FINISHED'|| $status == 'SUCCESS'))){
            if(isset($response['Body']['FeedDetail']['FeedErrors'])){
            	$error = $response['Body']['FeedDetail']['FeedErrors']['Error'];
            	if(!empty($error) && count($error) > 0){
		    		// loop thru error and set sellerSKU as array index to be used to determine which sync failed.
		    		if(!empty($error[0]) && is_array($error[0])) {
		    			foreach($error as $err) {
			    			$feedErrors[$err['SellerSku']][(int)$err['Code']] = $err['Message'];
			    		}
			    	}
			    	else{
			    		$feedErrors[$error['SellerSku']][(int)$error['Code']] = $error['Message'];
			    	}
		    	}
            }
            else{
            	// How to determine which sync failed if there is no seller sku to determine
                // $feed_id = $response['Body']['FeedDetail']['Feed'];
                // $error_desc[] = 'There is no Feed Errors, Refer to Seller Center on Feed Results  Status : '.$status.' | FeedID : '.$feed_id;
            }
        }

		// loop thru bulkSync to process it (add warning message, update status to fail, add failed message)
		// if no error is detected, update it to success
		// if sync is create product, check if all skus exist in marketplace, add create "update sku" syncs for missing skus
		// if action is success, update/create third party product/sku table.
		foreach($this->bulkSync as $index => $sync) {
	        $remarks = '';
			$errorFlag = false;
			$createProduct = false;
			if($sync->ref_table == 'Product') {
				$sellerSkus = ChannelSKU::select('channel_sku.channel_sku_id', 'channel_sku.sku_id', 'channel_sku.channel_id', 'channel_sku.product_id', 'sku.sku_id', 'sku.hubwire_sku', 'channel_sku.merchant_id')
                    ->leftJoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                    ->where('channel_sku.channel_id', $sync->channel_id)
                    ->where('channel_sku.product_id', $sync->ref_table_id)
                    ->get();

                if(strcasecmp($sync->action, 'createProduct') == 0){
                	$syncRepo = new SyncRepository;
		        	$product_id = $sync->ref_table_id;
		            $parent = null;
		            $merchantId = $sellerSkus[0]->product->merchant_id;
		            $skuId = $sellerSkus[0]->sku->sku_id;
		            $parent = $sellerSkus[0]->sku->hubwire_sku;
		            $skusNotInSellerCenter = array(
		               'channel_sku_id'        => array(),
		               'hubwire_sku'           => array()
		            );
		            $createProduct = true;
                }

                foreach($sellerSkus as $sellerSku) {
                	if(isset($feedErrors[$sellerSku->hubwire_sku])) {
                		$errorFlag = true;
                		foreach($feedErrors[$sellerSku->hubwire_sku] as $errorMsg) {
                			$remarks .= '[ERROR ('.$sellerSku->hubwire_sku.')]: ' . $errorMsg . ' ';
                		}
                	}

                	if(isset($feedWarnings[$sellerSku->hubwire_sku])) {
                		foreach($feedWarnings[$sellerSku->hubwire_sku] as $warningMsg) {
                			$remarks .= '[WARNING ('.$sellerSku->hubwire_sku.')]: ' . $warningMsg . ' ';
                		}
                	}

                	// if current sync action is create product, get number of sku that failed to create in marketplace
                	if($createProduct) {
                		if($skuId > $sellerSku->sku->sku_id) {
		                    $parent = $sellerSku->hubwire_sku;
		                    $merchantId = $sellerSku->merchant_id;
		                }

		                // check if sku exist by checking for error in the feed status, if yes, double check in marketplace
		                $exists = ($errorFlag) ? $this->getProductBySellerSku($sellerSku->hubwire_sku) : true;

			            if (!$exists) {
			            	$skusNotInSellerCenter['channel_sku_id'][] = $sellerSku->channel_sku_id;
			            	$skusNotInSellerCenter['hubwire_sku'][] = $sellerSku->hubwire_sku;
			            }
			            else {
			            	// sku successfully created in marketplace, update chnlSku's ref_id
			            	$sellerSku->ref_id = $sellerSku->channel_sku_id;
			            	$sellerSku->save();
			            }
                	}
                }

                // if current sync action is create product
                if($createProduct) {
                	if (count($skusNotInSellerCenter['channel_sku_id']) < count($sellerSkus)) {
		            	// one of the product's sku exists in seller center

		            	foreach ($skusNotInSellerCenter['channel_sku_id'] as $sku) {
			            	// create sync job for the rest of the skus that does not exists in seller center
			            	$skuSyncData['channel_sku_id'] = $sku;
			            	$skuSync = $syncRepo->updateSKU($skuSyncData);
			            	$this->customLog->addInfo("New sync created. | " . json_encode($skuSync));
			            }

		            	$pThirdParty_extra['parentSku'] = $parent;

			        	// Store product third party information for later reference
			            $productThirdParty = array(
							'ref_id' 			=> $product_id,
							'channel_id' 		=> $sync->channel_id,
							'third_party_name' 	=> $this->api_name,
							'product_id' 		=> $product_id,
							'extra'				=> json_encode($pThirdParty_extra)
						);

						ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

			            //Once product is created, create sync to upload media
						$input['product_id'] = $product_id;
						$input['channel_id'] = $sync->channel_id;
						$input['merchant_id'] = $merchantId;
						$input['trigger_event'] = $sync->trigger_event;
						$this->customLog->addInfo("Creating sync job for image upload. ", $input);

						$newSync = $syncRepo->updateMedia($input);
						$this->customLog->addInfo("New sync created. | " . json_encode($newSync));

						if ($errorFlag) {
							$remarks = "Seller Center Error: Create product failed but at least one of the SKUs already existed in Seller Center. New createSKU sync(s) have been created for ".(count($skusNotInSellerCenter['hubwire_sku']) > 0 ? implode(', ', $skusNotInSellerCenter['hubwire_sku']) : 'none.');
						}

						$errorFlag = false;
		            }
                }

			}elseif($sync->ref_table == 'ChannelSKU') {
				$sellerSku = ChannelSKU::select('channel_sku.channel_sku_id', 'channel_sku.sku_id', 'channel_sku.channel_id', 'channel_sku.product_id', 'sku.sku_id', 'sku.hubwire_sku')
                    ->leftJoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                    ->where('channel_sku.channel_id', $sync->channel_id)
                    ->where('channel_sku.channel_sku_id', $sync->ref_table_id)
                    ->first();

                if(isset($feedErrors[$sellerSku->hubwire_sku])){
            		$errorFlag = true;
            		foreach($feedErrors[$sellerSku->hubwire_sku] as $errorMsg) {
            			$remarks .= '[ERROR ('.$sellerSku->hubwire_sku.')]: ' . $errorMsg . ' ';
            		}
            	}

            	if(isset($feedWarnings[$sellerSku->hubwire_sku])){
            		foreach($feedWarnings[$sellerSku->hubwire_sku] as $warningMsg) {
            			$remarks .= '[WARNING ('.$sellerSku->hubwire_sku.')]: ' . $warningMsg . ' ';
            		}
            	}

            	// if sync action is createSKU and no error has been detected for this seller SKU, update chnl SKU's ref_id
	            if (!$errorFlag && strcasecmp($sync->action, 'createSKU') == 0 && strcasecmp($sync->ref_table, 'ChannelSKU') == 0)
			        {
			            $sellerSku->ref_id = $sellerSku->hubwire_sku;
			            $sellerSku->save();
			        }
				}

			// if an error is flagged for this sync, update its status to fail
        	if($errorFlag) {
            	$this->bulkSync[$index]->status = 'FAILED';
            }else{
            	$this->bulkSync[$index]->status = 'SUCCESS';
            }

			$this->bulkSync[$index]->remarks = $remarks;
			$this->bulkSync[$index]->save();

			ThirdPartySync::updateSyncStatus($this->bulkSync[$index]);
		}
    }

    public function feedStatus()
    {
        $params['Action'] = 'FeedStatus';
        $params['FeedID'] = $this->sync->request_id;
        $channel = Channel::with('channel_detail')->find($this->sync->channel_id);
        $request = $this->api($channel);
        $remarks = "";
        $data =array();
        $this->error_data['handler']['data'] = $data;

        $response = $request('GET', $params, $data, $this->sync->sync_id);
        $this->error_data['handler']['response'] = $response;

        if(!$response['success'] && strcasecmp($this->sync->action, 'createProduct') == 0) {
        	$this->sync->status = 'PROCESSING';
	    	$this->sync->save();
        }
        else if (!$response['success'] && strcasecmp($this->sync->action, 'createProduct') != 0) {
            return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
        }

        if(empty($response['Body']['FeedDetail']))
        {
            //if server error do nothing and try again later
            return;
        }

        if(strcasecmp($this->sync->action, 'createProduct') == 0 && strcasecmp($this->sync->ref_table, 'Product') == 0)
        {
        	$syncRepo = new SyncRepository;
            $product_id = $this->sync->ref_table_id;
            // Get all the channel SKU for the product
            $channel_skus = ChannelSKU::where('product_id', '=', $product_id)
                            ->where('channel_id', '=', $this->sync->channel_id)
                            ->with('product', 'sku')
                            ->get();

            $skusNotInSellerCenter = array(
            	'channel_sku_id'	=> array(),
            	'hubwire_sku'		=> array()
            );
            $skuId = $channel_skus[0]->sku->sku_id;
            $parent = $channel_skus[0]->sku->hubwire_sku;
            $merchantId = $channel_skus[0]->product->merchant_id;

            foreach($channel_skus as $channel_sku)
            {
                $hubwireSku = $channel_sku->sku->hubwire_sku;

                if($skuId > $channel_sku->sku->sku_id) {
                    $skuId = $channel_sku->sku->sku_id;
                    $parent = $hubwireSku;
                    $merchantId = $channel_sku->product->merchant_id;
                }

                $exists = (!$response['success']) ? $this->getProductBySellerSku($hubwireSku) : true;

                if (!$exists) {
                	$skusNotInSellerCenter['channel_sku_id'][] = $channel_sku->channel_sku_id;
                	$skusNotInSellerCenter['hubwire_sku'][] = $hubwireSku;
                }
                else {
                	$channel_sku->ref_id = $channel_sku->channel_sku_id;
                	$channel_sku->save();
                }
            }

            if (count($skusNotInSellerCenter['channel_sku_id']) < count($channel_skus)) {
            	// one of the product's sku exists in seller center

            	foreach ($skusNotInSellerCenter['channel_sku_id'] as $sku) {
	            	// create sync job for the rest of the skus that does not exists in seller center
	            	$skuSyncData['channel_sku_id'] = $sku;
	            	$skuSync = $syncRepo->updateSKU($skuSyncData);
	            	$this->customLog->addInfo("New sync created. | " . json_encode($skuSync));
	            }

            	$pThirdParty_extra['parentSku'] = $parent;

	        	// Store product third party information for later reference
	            $productThirdParty = array(
					'ref_id' 			=> $product_id,
					'channel_id' 		=> $this->sync->channel_id,
					'third_party_name' 	=> $this->api_name,
					'product_id' 		=> $product_id,
					'extra'				=> json_encode($pThirdParty_extra)
				);

				ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

	            //Once product is created, create sync to upload media
				$input['product_id'] = $product_id;
				$input['channel_id'] = $this->sync->channel_id;
				$input['merchant_id'] = $merchantId;
				$input['trigger_event'] = $this->sync->trigger_event;
				$this->customLog->addInfo("Creating sync job for image upload. ", $input);

				$newSync = $syncRepo->updateMedia($input);
				$this->customLog->addInfo("New sync created. | " . json_encode($newSync));

				if (!$response['success']) {
					$remarks = "Seller Center Error: Create product failed but at least one of the SKUs already existed in Seller Center. New createSKU sync(s) have been created for ".(count($skusNotInSellerCenter['hubwire_sku']) > 0 ? implode(', ', $skusNotInSellerCenter['hubwire_sku']) : 'none.');
				}
            }
            else {
            	$this->sync->status = 'FAILED';
	    		$this->sync->save();
            	return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
            }
        }
        else if (strcasecmp($this->sync->action, 'createSKU') == 0 && strcasecmp($this->sync->ref_table, 'ChannelSKU') == 0)
        {
            $channel_sku = ChannelSKU::with('sku')->findOrFail($this->sync->ref_table_id);
            $hubwireSku = $channel_sku->sku->hubwire_sku;
            $channel_sku->ref_id = $hubwireSku;
            $channel_sku->save();
        }

        // failed sync stops here
        if(!empty($response['Body']['FeedDetail']['FeedWarnings']))
        {
            if(!empty($response['Body']['FeedDetail']['FeedWarnings']['Warning'][0]) && is_array($response['Body']['FeedDetail']['FeedWarnings']['Warning'][0]))
            {
                foreach($response['Body']['FeedDetail']['FeedWarnings']['Warning'] as $detail)
                {
                    foreach ($detail as $key => $value)
                    {
                        $remarks .= "(".$key.") ".$value.'<br>';
                    }
                    $remarks .='<br>';
                }
            }
            else
            {
                //$remarks.= $response['Body']['FeedDetail']['Warning']['Message'];
           		$remarks .= 'There is no Feed Errors, Refer to Seller Center on Feed Status.';
	 		}
        }
        // loop thru feed warnings and append remarks to each sync
        // get from db where sync != failed && request_id = given request_id -> check on whether it exist in marketplace -> set all others to success

        $this->sync->remarks = !empty($remarks) ? $remarks : $this->sync->remarks;
        $this->sync->status = 'SUCCESS';
        $this->sync->save();
    }

    public function getProductsQty(array $product_skus, $channel)
    {
        $params = array();
        $params['Action'] = 'GetProducts';
        $params['SkuSellerList'] = json_encode($product_skus);
        $return = array();
        $sellerCenter = $this->api($channel);
        $response = $sellerCenter('GET', $params, array());

        if(!$response['success']) {
            return $response;
        }

        if(!isset($response['Body'])){
            $error = array();
            $error['error_desc'] = "Response Body Not Found.";
            $error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
            return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }

        if(!empty($response['Body']['Products']['Product'])){
            foreach($response['Body']['Products']['Product'] as $product){
                if(isset($product['SellerSku'])){
                    $return[] = array(
                        'chnlSku'       =>  $product['SellerSku'],
                        'stock_qty'     =>  $product['Available'],
                        'status'        =>  $product['Status']
                    );
                }
            }
        }

        return $return;
    }

    // bulk sync functions
    public function bulkCreate(array $bulkSync)
    {
    	foreach($bulkSync['syncData'] as $sync) {
        	$this->bulkSync[$sync['sync']->id] = $sync['sync'];
    	}

    	$this->createSku($bulkSync, true);
    }

    public function bulkUpdate(array $bulkSync)
    {
    	foreach($bulkSync['syncData'] as $sync) {
        	$this->bulkSync[$sync['sync']->id] = $sync['sync'];
    	}

    	$this->updateSku($bulkSync, true);
    }

    public function bulkMedia(array $bulkSync)
    {
    	foreach($bulkSync['syncData'] as $sync) {
        	$this->bulkSync[$sync['sync']->id] = $sync['sync'];
    	}

    	$this->updateImages($bulkSync, true);
    }

    public function bulkQty(array $bulkSync)
    {
    	foreach($bulkSync['syncData'] as $sync) {
        	$this->bulkSync[$sync['sync']->id] = $sync['sync'];
    	}

    	$this->updateQuantity($bulkSync, true);
    }

    public function bulkPrice(array $bulkSync)
    {
    	foreach($bulkSync['syncData'] as $sync) {
        	$this->bulkSync[$sync['sync']->id] = $sync['sync'];
    	}

    	$this->updatePrice($bulkSync, true);
    }
}
