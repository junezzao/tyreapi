<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Controllers\Admin\AdminController;
use GuzzleHttp\Exception\RequestException as RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

use App\Repositories\Eloquent\SyncRepository;
use App\Modules\ThirdParty\Repositories\LazadaRepo;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;

use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Modules\ThirdParty\Config;

use App\Models\Admin\ReservedQuantity;
use App\Models\Admin\ThirdPartySync;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\Document;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ProductMedia;

use Monolog;
use DateTime;
use Carbon\Carbon;
use SimpleXMLElement;
use Exception;
use Response;
use DateTimezone;
use Image;
use App\Services\MediaService as MediaService;

class LazadaScController extends AdminController implements MarketplaceInterface
{
	public $channel, $api_name, $__api, $customLog, $sync, $order;
	private $error_data = array();
	private $livePriceSkus = array();

	public function __construct(){
		$this->api_name = 'LazadaSC';

		$this->customLog = new Monolog\Logger('LazadaSC Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/lazada_sc.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error LazadaSC';
		$this->error_data['File'] = __FILE__;
	}

	public function exceptionHandler($e) {
		$error['subject'] = 'Error '. get_class($this);
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

		$error['subject'] = 'Error '. get_class($this);
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
		}

		$this->customLog->addError(json_encode($this->error_data));
		$this->ErrorAlert($this->error_data);
	}

	public function initialize($channel, ThirdPartySync $sync = null)
	{
		$this->__setApi($channel);
		$this->sync = is_null($sync) ? null : $sync;
		return $this;
	}

	private function array_to_xml($array_info, &$xml_info) {
		foreach($array_info as $key => $value) {
			if(is_array($value)) {
	        	// check the children
				for (reset($value); is_int(key($value)); next($value));
					$onlyIntKeys = is_null(key($value));
				if($onlyIntKeys){
					if ($key == 'Skus') {
						$parentNode = $xml_info->addChild("Skus");

						foreach($value as $values){
							$subnode = $parentNode->addChild("Sku");
							$this->array_to_xml($values, $subnode);
						}
					}
					else if ($key == 'Images') {
						$parentNode = $xml_info->addChild("Images");

						foreach($value as $values){
							$parentNode->addChild("Image", htmlspecialchars("$values"));
						}
					}
					else {
						foreach($value as $values){
							$subnode = $xml_info->addChild("$key");
							$this->array_to_xml($values, $subnode);
						}
					}
				}
				else {
					$subnode = $xml_info->addChild("$key");
					$this->array_to_xml($value, $subnode);
				}
			}
			else {
				$xml_info->addChild("$key", htmlspecialchars("$value"));
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

	public function api($channel = null) {
		if(!empty($channel)){
			$this->__setApi($channel);
		}
		return $this->__api;
	}

	private function __setApi($channel) {
		$this->channel = $channel;
		$this->__api = function($method, $url_params = array() , $params = array(), $sync_id = null) use ($channel)
		{
			try{
				$url = '';
				$ordersActions = ['GetOrders', 'GetDocument', 'GetOrderItems', 'GetMultipleOrderItems', 'SetStatusToReadyToShip'];

				// The current time. Needed to create the Timestamp parameter below.
				$now = new DateTime();

				// The parameters for our GET request. These will get signed.
				$url_params['UserID'] = $channel->channel_detail->api_password;
				$url_params['Version'] = '1.0';
				$url_params['Timestamp'] = $now->format(DateTime::ISO8601); //2016-10-06T16:52:11+08:00
				$url_params['Format'] = 'JSON';

				// Sort parameters by name.
				ksort($url_params);

				// URL encode the parameters.
				$encoded = array();
				foreach ($url_params as $name => $value) {
				    $encoded[] = rawurlencode($name) . '=' . rawurlencode($value);
				}

				// Concatenate the sorted and URL encoded parameters into a string.
				$concatenated = implode('&', $encoded);

				// The API key for the user as generated in the Seller Center GUI.
				// Must be an API key associated with the UserID parameter.
				$api_key = $channel->channel_detail->api_key;

				// Compute signature and add it to the parameters.
				$url_params['Signature'] = rawurlencode(hash_hmac('sha256', $concatenated, $api_key, false));

				$url = $channel->channel_detail->api_secret . '?';
				$url .= http_build_query($url_params, '', '&', PHP_QUERY_RFC3986);
				//\Log::info($url);
				// Build XML request body
				$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Request></Request>");
				$this->array_to_xml($params, $xml);
				$xml_str = $xml->asXML();
				$this->error_data['url'] = $url;
				$this->error_data['xml_body'] = $xml_str;

				// Log request
				$logInfo = "";
				$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
				$logInfo .= "URL: " . $url . " | ";
				$logInfo .= "Request Body/Query: " . $xml_str;
				$this->customLog->addInfo($logInfo);

				$guzzle = new Client(array( 'curl' => array(CURLOPT_SSL_VERIFYPEER => false) ));
				$requestHeaders["Content-Type"] = "application/x-www-form-urlencoded";
				\Log::info('method...' .print_r($method, true));
				\Log::info('url...' .print_r($url, true));
				\Log::info('requestHeaders...' .print_r($requestHeaders, true));
				\Log::info('xml_str...' .print_r($xml_str, true));
				die();
				$request = new GuzzleRequest($method, $url, $requestHeaders);
				$response = $guzzle->send($request, array('body' => $xml_str, 'http_errors' => false));
			}
			catch (RequestException $e) {
				$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;

				$response = $e->getResponse();
				$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';

				$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

				if ($e->hasResponse()) {
					$this->customLog->addError(json_encode($e->getResponse(), true));
			    }

				return false;
			}
			catch(Exception $e) {
				$message = "Error send request. Unknown error. Request URL: " . $url;
				$message .= ' Error: ' . $e->getMessage() . ' at line ' . $e->getLine();

				$this->customLog->addError($message);

			    $this->errorHandler(__FUNCTION__, $e->getLine(), (!empty($response) ? $response : null), $message);
				return false;
			}

			// $response = simplexml_load_string($response->getBody()->getContents());
			// $response = $this->xml2array($response);
			$response = json_decode($response->getBody()->getContents(), true);

			$response['SuccessResponse']['success'] = true;

			if(!empty($response['ErrorResponse'])) {
				$response = $response['ErrorResponse'];
				$error_desc = array();

				if(!empty($response['Head']['ErrorMessage'])) {
					$error_desc[] = $response['Head']['ErrorMessage'];
				}

				if(!empty($response['Body']['Errors'])) {
					$error_desc[] = $response['Body']['Errors'];
				}

				$message = "Seller Center Error: " . json_encode($error_desc);
				$message .= " | Request URL: " . $url;
				$this->error_data['response'] = $response;
				$this->error_data['message'] = json_encode($error_desc);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);

				if ($response['Head']['ErrorCode'] == 429 && !empty($this->sync)) {
					$this->sync->status = 'RETRY';
					$this->sync->remarks = 'Seller Center Error: E429 Too many requests';
					$this->sync->save();
				}

				if (in_array($url_params['Action'], $ordersActions)) {
					$response['success'] = false;
					return $response;
				}
				else {
					return false;
				}
			}
			else {
				return $response['SuccessResponse'];
			}
		};
	}

	/*
	 *
	 * Orders functions
	 *
	 */
	public function setOrder($order)
	{
		$this->order = $order;
	}

	public function getOrders(array $filter) {
		$params = array();
		$params['Action'] = 'GetOrders';

		$response = LazadaRepo::prepareOrderRequest(MpUtils::getTypeCode('GET_ORDER'), $filter);
		if(!$response['success'])
		{
			return $response;
		}

		$date = new DateTime($response['param']['startTime'], new DateTimezone('Asia/Kuala_Lumpur'));
		$date->setTimezone(new DateTimezone('UTC'));
		$dateEnd = new DateTime($response['param']['endTime'], new DateTimezone('Asia/Kuala_Lumpur'));
		$dateEnd->setTimezone(new DateTimezone('UTC'));
		$params['CreatedAfter'] = $date->format(DateTime::ISO8601);
		$params['CreatedBefore'] = $dateEnd->format(DateTime::ISO8601);//new add code

		$sellerCenter = $this->api($this->channel);
		$response = $sellerCenter('GET', $params, array());

		if(isset($response['success']) && !$response['success']) {
			return $response;
		}

		$orders = array();

		if(!isset($response['Body'])) {
			$error = array();
			$error['error_desc'] = "Response Body Not Found.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}
		else if(count($response['Body']['Orders']) == 0) {
			$error = array();
			$error['error_desc'] = "Order is empty.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}

		foreach($response['Body']['Orders'] as $order){
			$orders[$order['OrderId']] = $order;
		}

		$response2 = $this->getMultipleOrderItems($orders, $this->channel);
		if(!$response2['success']) {
			return $response2; //failed to get Multiple order items.
		}

		return LazadaRepo::processOrder($orders, $this->channel);
	}

	public function getDocumentDemand($item_id,$channel_id, $type) {
		$channel = Channel::with('channel_detail')->find($channel_id);
		$item = OrderItem::findOrFail($item_id);
		$order = Order::findOrFail($item->order_id);

		$document_types = array('invoice','shippingLabel');
		$params['Action'] = 'GetDocument';

		$refs = array();
		foreach($order->items as $item)
		{
			if (in_array($item->status, ['Picked', 'Verified']) && $item->quantity > 0)
				$refs[] = $item->tp_item_id;
		}
		$params['OrderItemIds'] = '['. implode(',', $refs) .']';
		$params['DocumentType'] = $type;

		$data = array();
		$sellercenter = $this->api($channel);
		$this->error_data['handler']['params'] = $params;
		$this->error_data['handler']['data'] = $data;

		$response = $sellercenter('GET', $params, $data);

		$this->error_data['handler']['response'] = $response;
		if(isset($response['success']) && !$response['success']){
			$response['channel'] = $this->channel;
			$this->customLog->addError(__FUNCTION__.' >> Throwing exception.');
			return Response::JSON($response, MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		$file = base64_decode($response['Body']['Document']['File']);
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
		if(isset($response['success']) && !$response['success']){
			return $response;
		}

		if(!isset($response['Body'])){
			$error = array();
			$error['error_desc'] = "Response Body Not Found.";
			$error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
			return MpUtils::errorResponse($error, __METHOD__, __LINE__);
		}

		foreach($response['Body']['Orders'] as $order){
			if(count($order['OrderItems']) == 0) continue;

			$orders[$order['OrderId']]['OrderItems'] = $order['OrderItems'];
		}

		return array('success' => true);
	}

	public function getShippingProviderName() {
		$params['Action'] = 'GetShipmentProviders';

		$sellerCenter = $this->api($this->channel);
		$response = $sellerCenter('GET', $params, array());

		if ((isset($response['success']) && !$response['success']) || $response === false) {
			return false;
		}
		else {
			$paymentType = $this->order->payment_type;
			$cod = (strcasecmp($paymentType, 'CashOnDelivery') == 0) ? 1 : 0;

			foreach ($response['Body']['ShipmentProviders'] as $provider) {
				if ($provider['Cod'] == $cod) {
					return $provider['Name'];
				}
			}

			return false;
		}
	}

	private function checkOrderItemsForRTS($tpOrderId, array $tpItemIds) {
		$items = $this->getOrderItems($this->channel, $tpOrderId);

		foreach ($items as $item) {
			if (in_array($item['OrderItemId'], $tpItemIds) && strcasecmp($item['Status'], 'pending') != 0) {
				return ['success' => false, 'error' => 'All ready-to-ship items must have status "Pending" in Lazada.'];
			}
		}

		return ['success' => true];
	}

	public function readyToShip($input) {
		$response = [];
		try
		{
			$response = '';
			$this->error_data['handler']['input'] = $input;

			$refs = array();
			foreach($this->order->items as $item)
			{
				if (in_array($item->status, ['Picked', 'Verified']) && $item->quantity > 0)
					$refs[] = $item->tp_item_id;
			}

			if (count($refs) == 0) {
				throw new Exception('No items to ship.');
			}

			$data = array();
			$params['Action'] 			  = 'SetStatusToReadyToShip';
			$params['OrderItemIds'] 	  = '['. implode(',', $refs) .']';
			$params['DeliveryType'] 	  = Config::get('sellerCenter.default.DeliveryType');
			// $params['DeliveryType'] 	  = 'pickup'; // uncomment this line when testing on zalora staging order

			$check = $this->checkOrderItemsForRTS($this->order->tp_order_id, $refs);

			if (!$check['success']) {
				throw new Exception($check['error']);
			}

			$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
			$shippingProvider = ($this->order->payment_type == 'CashOnDelivery') ? $extra_info['shipping_provider_cod'] : $extra_info['shipping_provider'];

			if ($shippingProvider === false) {
				$error = 'There was a problem getting the shipping provider.';
				throw new Exception($error);
			}

			$params['ShippingProvider'] = $shippingProvider;

			if(!empty($input['tracking_no'])) $params['TrackingNumber'] = $input['tracking_no'];

			$this->error_data['handler']['params'] = $params;
			$this->error_data['handler']['data'] = $data;

			$sellerCenter = $this->api($this->channel);
			$this->customLog->addInfo('ReadyToShip sending Guzzle request... '. json_encode($params, true));
			$response = $sellerCenter('POST', $params, $data);

			$this->customLog->addInfo('ReadyToShip response... '. json_encode($response, true));
			$this->error_data['handler']['response'] = $response;

			$response2 = $this->getConsignmentNumber($this->channel, $this->order->tp_order_id);

			if(isset($response2['success']) && !$response2['success']) {
				$error = $response2['message'];
				$error .= ((isset($response['success']) && !$response['success']) && !isset($response['Head'])) ? (' | ' . $response['error_desc']) : ((isset($response['Head'])) ? ' | ' . $response['Head']['ErrorMessage'] : '');

				throw new Exception($error);
			}

			return $response2;
		}
		catch(Exception $e)
		{
			$message = 'Error: '. $e->getMessage() .' in '. $e->getFile() .' at line: '. $e->getLine();

			if (!empty($this->error_data['response']) && empty($response)) {
				$response = !empty($response2) ? $response2 : $this->error_data['response'];
			}

			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);

			return array('success' => false, 'message' => $e->getMessage());
		}
	}

	public function getConsignmentNumber($channel, $tp_order_id) {
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

	public function getOrderItems($channel, $tp_order_id) {
		$params['Action'] = 'GetOrderItems';
		$params['OrderId'] = $tp_order_id;

		$data = array();
		$sellerCenter = $this->api($channel);
		$response = $sellerCenter('GET', $params, $data);
		if(isset($response['success']) && !$response['success']){
			$response['channel'] = $this->channel;
			$this->customLog->addError(__FUNCTION__ . ' >> Throwing exception.');
			return array('success' => false, 'message' => $this->error_data['message']);
		}

		return $response['Body']['OrderItems'];
	}

	public function setSalesStatus($order_id, array $details) {}

	// END - ORDERS FUNCTIONS

	/*
	 *
	 * Products functions
	 *
	 */

    /**
	 * Get Categories
	 * @return array $return
	 */
	public function getCategories() {
		$params['Action'] = 'GetCategoryTree';
		$lazada = $this->api();

		$response = $lazada('GET', $params);

		if($response === false){
			return array('success' => false, 'error' => $this->error_data['response']['Head']['ErrorMessage']);
		}

        $response = $this->_map_categories($response['Body']);

        return array('success' => true, 'categories' => $response);
	}

	private function _map_categories($categories, $pieces = array(), $depth = 1) {
		$categoriesArray = array();

		foreach ($categories as $category) {
			if (!empty($category['children'])) {
				$pieces[$depth] = $category['name'];
				ksort($pieces);
				$categoriesArray[$category['categoryId']] = implode('/', $pieces);

				$depth++;
				$nested = $this->_map_categories($category['children'], $pieces, $depth);
				$categoriesArray += $nested;
				array_pop($pieces);
				$depth--;
			}
			else {
				$pieces[$depth] = $category['name'];
				ksort($pieces);
				$categoriesArray[$category['categoryId']] = implode('/', $pieces);
			}
		}

		return $categoriesArray;
	}

	public function getProductBySellerSku($hubwireSku) {
		$params['Action'] = 'GetProducts';
		$params['SkuSellerList'] = '["' . $hubwireSku . '"]';
	    $lazada = $this->api();

	    $response = $lazada('GET', $params, array(), $this->sync->id);

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

	public function checkExistInSellerCenter(array $product) {
		$skusNotInSellerCenter = array();
		$productExists = false;

		foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
			if ($this->getProductBySellerSku($sku['hubwire_sku'])) {
				$productExists = true;
			}
			else {
				$skusNotInSellerCenter[] = $channelSkuId;
			}
		}

		if ($productExists) {
			$syncRepo = new SyncRepository;

			foreach ($skusNotInSellerCenter as $channelSkuId) {
				$skuSyncData['channel_sku_id'] = $channelSkuId;
				$skuSyncData['trigger_event'] = $this->sync->trigger_event;
            	$skuSync = $syncRepo->updateSKU($skuSyncData);
            	$this->customLog->addInfo("New sync created. | " . json_encode($skuSync));
			}
		}

		return array('product_exists' => $productExists, 'skus_not_in_sc' => $skusNotInSellerCenter);
	}

	public function createProduct(array $product, $bulk = false, $createSku = false) {
		try {
			$data = $this->prepareProduct($product);

			if(!$data['success']) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
			}

			$params['Action'] = 'CreateProduct';
	    	$lazada = $this->api();

	    	$response = $lazada('POST', $params, $data['product'], $this->sync->id);

	    	if($response === false) {
	    		$this->sync->status = 'PROCESSING';
	    		$this->sync->save();

	    		$productCheck = $this->checkExistInSellerCenter($product);

	    		if (!$productCheck['product_exists']) {
	    			$this->sync->status = 'FAILED';
	    			$this->sync->save();
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
	    		}
			}

			if (!$createSku) {
				// Store thirdparty info for product
				$productThirdParty = array(
					'ref_id' 			=> $product['id'],
					'channel_id' 		=> $this->sync->channel_id,
					'third_party_name' 	=> $this->api_name,
					'product_id' 		=> $product['id'],
					'extra'				=> json_encode(array('parentSku' => $data['product']['Product']['AssociatedSku']))
				);

				ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);
			}

			// Store thirdparty info for channel skus
			$skusNotInSellerCenter = array();
			foreach ($product['sku_in_channel'] as $channelSkuId => $sku) {
				if ($response === false && in_array($channelSkuId, $productCheck['skus_not_in_sc'])) {
					$skusNotInSellerCenter[] = $sku['hubwire_sku'];
					continue;
				}

				$sku = array(
					'hubwire_sku'	=> $sku['hubwire_sku'],
					'merchant_id'	=> $product['merchant_id'],
					'channel_id'	=> $this->channel->id,
					'ref_id'		=> $channelSkuId,
					'product_id'	=> $product['id']
				);

				$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($sku);

				if(!$storeResponse){
					$message = 'Error storing ref_id for channel sku: SKU ' . $sku['hubwire_sku'] . ' | data sent: ' . json_encode($data['product']);
					$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
					return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
				}
			}

			$response = ($response === false) ? "Seller Center Error: Create product failed but at least one of the SKUs already existed in Seller Center. New createSKU sync(s) have been created for ".(count($skusNotInSellerCenter) > 0 ? implode(', ', $skusNotInSellerCenter) : 'none.') : $response;
			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);

			if (count($product['images']) > 0) {
				// Create a new sync for image upload
				if ($createSku) {
					$channelSku = ChannelSKU::findOrFail($this->sync->ref_table_id);
					$input['product_id'] = $channelSku->product_id;
				}
				else {
					$input['product_id'] = $this->sync->ref_table_id;
				}

				$input['channel_id'] = $this->channel->id;
				$input['merchant_id'] = $product['merchant_id'];
				$input['trigger_event'] = $this->sync->trigger_event;
				$this->customLog->addInfo("Creating sync job for image upload. ", $input);
				$syncRepo = new SyncRepository;
				$newSync = $syncRepo->updateMedia($input);
				$this->customLog->addInfo("New sync created. | " . json_encode($newSync));
			}
		}
		catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}
	}

    public function updateProduct(array $product, $bulk = false) {
    	try {
    		$data = $this->prepareProduct($product, false);

    		if(!$data['success']) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
			}

			$params['Action'] = 'UpdateProduct';
	    	$lazada = $this->api();

	    	$response = $lazada('POST', $params, $data['product'], $this->sync->id);

	    	if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			// Store thirdparty info for channel skus
			foreach ($product['sku_in_channel'] as $sku) {
				if (empty($sku['channel_sku_ref'])) {
					$sku = array(
						'hubwire_sku'	=> $sku['hubwire_sku'],
						'merchant_id'	=> $product['merchant_id'],
						'channel_id'	=> $this->channel->id,
						'ref_id'		=> $sku['hubwire_sku'],
						'product_id'	=> $product['id']
					);

					$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($sku);

					if(!$storeResponse){
						$message = 'Error storing ref_id for channel sku: SKU ' . $sku['hubwire_sku'] . ' | data sent: ' . json_encode($data['product']);
						$this->errorHandler(__FUNCTION__, __LINE__, $response, $message);
						return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
					}
				}
			}
			
			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}
    }

    public function updatePrice(array $sku, $bulk = false) {
    	try {
    		if (empty($sku['channel_sku_ref'])) {
				$this->errorHandler(__FUNCTION__, __LINE__, null, 'This sku does not exist in marketplace.');
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
			}

			$price = $this->preparePrice($sku);
			$data['Product']['Skus'][] = array(
				'SellerSku'		=> $sku['hubwire_sku'],
				'Price'			=> $price['price'],
				'SalePrice'		=> $price['special_price'],
				'SaleStartDate'	=> $price['special_from_date'],
				'SaleEndDate'	=> $price['special_to_date']
			);

			$params['Action'] = 'UpdatePriceQuantity';
	    	$lazada = $this->api();

	    	$response = $lazada('POST', $params, $data, $this->sync->id);

	    	if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}
    }

    public function updateVisibility(array $product, $bulk = false) {}

    public function deleteProduct(array $product, $bulk = false) {}

    public function createSku(array $sku, $bulk = false) {
		return $this->createProduct($sku, false, true);
    }

    public function updateSku(array $sku, $bulk = false) {
		return $this->updateProduct($sku);
    }

    public function updateQuantity(array $sku, $bulk = false) {
    	try {
    		$data = $this->prepareQuantity($sku);

	    	if(!$data['success']) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('DATA_ERROR'));
			}

			$params['Action'] = 'UpdatePriceQuantity';
	    	$lazada = $this->api();

	    	$response = $lazada('POST', $params, $data['product'], $this->sync->id);

	    	if($response === false){
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();

			ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    	}
    	catch (Exception $e) {
			$message = 'Error: '. $e->getMessage().' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), null, $message);
		}

		// $this->updateProduct($sku);
    }

    public function deleteSku(array $sku, $bulk = false) {}

    public function migrateImage(array $image) {
    	$dimensions = Config::get('marketplace.image_size.square');

    	$image_path = $image['path'] . '_' . $dimensions['width'] . 'x' . $dimensions['height'];
    	$data['Image']['Url'] = $image_path;

		$image_headers = @get_headers($image_path);

		// If image 600x600 is not found
		if(!$image_headers || $image_headers[0] == 'HTTP/1.1 403 Forbidden') {
		    $path = "/tmp/".uniqid();
		    $product_media = ProductMedia::findOrFail($image['id']);
		    $width = 600;
		    $height = 600;
		    $fileName = str_replace($product_media->media->ext, '', $product_media->media->filename);
		    $extension = str_replace('.', '', $product_media->media->ext);

            // Generate square image
            $background = Image::canvas($width, $height);
            $squaredImage = Image::make($product_media->media->media_url.$product_media->media->ext)->resize($width, $height, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            });

            // Get content type for original image
            $contentType = get_headers($product_media->media->media_url.$product_media->media->ext, 1)["Content-Type"];

            $background->insert($squaredImage, 'center');
            $background->encode('png')->save($path, 100);

            $mediaKey = $fileName.'_'.$width.'x'.$height;
            $mediaService = new MediaService(false, true);
            $s3Upload = $mediaService->uploadFileToS3($path, $mediaKey, true, $contentType);
		}

    	$params['Action'] = 'MigrateImage';
		$lazada = $this->api();
		$response = $lazada('POST', $params, $data, $this->sync->id);

		if ($response === false) {
			return false;
		}

		return $response['Body']['Image']['Url'];
    }

    public function updateImages(array $product) {
    	try {
			// $data['Product']['Skus'][0]['SellerSku'] = $product['parent'];

			// foreach ($product['images'] as $image) {
			// 	$data['Product']['Skus'][0]['Images'][] = $image['path'] . '_' . $dimensions['width'] . 'x' . $dimensions['height'];
			// }

			// Upload same images for every skus
			$i = 0;
			foreach($product['sku_in_channel'] as $sku) {
				$data['Product']['Skus'][$i]['SellerSku'] = $sku['hubwire_sku'];

				foreach ($product['images'] as $index => $image) {
					if (empty($image['external_url'])) {
						// migrate image to Lazada bucket
						$url = $this->migrateImage($image);

						if ($url === false) {
							return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
						}

						$product['images'][$index]['external_url'] = $url;
					}

					$data['Product']['Skus'][$i]['Images'][] = $product['images'][$index]['external_url'];
				}

				$i++;
    		}

			$params['Action'] = 'SetImages';
			$lazada = $this->api();
			$response = $lazada('POST', $params, $data, $this->sync->id);

			if ($response === false) {
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}

			foreach ($product['images'] as $image) {
				if (empty($image['image_ref'])) {
					$imageInfo = array(
						'media_id'			=> $image['id'],
						'ref_id'			=> 0,
						'channel_id'		=> $this->channel->id,
						'third_party_name'	=> 'Lazada',
						'external_url'		=> $image['external_url']
					);

					ProductSyncRepo::storeMediaThirdPartyInfo($imageInfo);
				}
			}

			$this->sync->remarks = json_encode($response);
			$this->sync->status = 'SUCCESS';
			$this->sync->save();
    	}
    	catch (Exception $e) {
    		$message = 'Error: ' . $e->getMessage() . ' at line: ' . $e->getLine();
			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message);
    	}
    }

    public function prepareQuantity(array $sku) {
		if (empty($sku['channel_sku_ref'])) {
			$this->errorHandler(__FUNCTION__, __LINE__, null, 'This sku does not exist in marketplace.');
			return ['success' => false];
		}

    	$temp_qty = $sku['quantity'];
    	$data_product = array(
			'SellerSku'	=> $sku['hubwire_sku'],
			'Quantity'	=> $temp_qty,
		);

		// check if channel_sku has sold quantity
		$reserved_qty = ReservedQuantity::where('channel_sku_id', '=', $this->sync->ref_table_id)->first();

		if(!empty($reserved_qty)) {
			$temp_qty += $reserved_qty->quantity;
		}

		//if (!empty($temp_qty)) {
			$data_product['Quantity'] = ($temp_qty < 0) ? 0 : $temp_qty;
		//}

		$data['Product']['Skus'][] = $data_product;
		return ['success' => true, 'product' => $data];
    }

    public function prepareProduct(array $product, $isCreate = true) {
    	if (empty($product['category'])) {
			$message = 'Please assign category for this product.';
			$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
			return array('success' => false, 'error' => $this->error_data);
		}

		$data['Product'] = array(
			'PrimaryCategory'		=> $product['category'],
			'Status'				=> ($product['is_active']) ? 'active' : 'inactive',
			'Attributes'			=> array(
				'name'				=> $product['name'],
				'description'		=> $product['description'],
				'short_description'	=> (!empty($product['short_description'])) ? $product['short_description'] : '',
				'brand'				=> $product['brand'],
			),
		);

		if($isCreate) {
			if (empty($product['parent'])) {
				reset($product['sku_in_channel']);
				$firstChannelSkuId = key($product['sku_in_channel']);

				$product['parent'] = $product['sku_in_channel'][$firstChannelSkuId]['hubwire_sku'];
			}

			$data['Product']['AssociatedSku'] = $product['parent'];
		}

    	$i = 0;
    	$productQuantity = 0;
    	$skus = array();
    	foreach($product['sku_in_channel'] as $channel_sku_id => $channel_sku)
    	{
    		$sku_weight_kg = $channel_sku['weight'] * 0.001;
			$skus[$i] = array(
				'SellerSku'			=> $channel_sku['hubwire_sku'],
				'color_family'		=> ucfirst($channel_sku['options']['Colour']),
				'size'				=> $channel_sku['options']['Size'],
				'quantity'			=> $channel_sku['quantity'],
				// 'product_weight'	=> $sku_weight_kg,
				'package_height'	=> 1,
				'package_length'	=> 1,
				'package_width'		=> 1,
				'package_weight'	=> round($sku_weight_kg, 2),
				'package_content'	=> '1 x ' . $product['name'],
			);

			$price = $this->preparePrice($channel_sku);
			$skus[$i]['price']				= $price['price'];
			$skus[$i]['special_price'] 		= $price['special_price'];
			$skus[$i]['special_from_date'] 	= $price['special_from_date'];
			$skus[$i]['special_to_date'] 	= $price['special_to_date'];

			$temp_qty = $channel_sku['quantity'];

			// check if channel_sku has sold quantity
			$reserved_qty = ReservedQuantity::where('channel_sku_id', '=', $channel_sku_id)->first();

			if(!empty($resevered_qty)) {
				$temp_qty += $reserved_qty->quantity;
			}

			$skus[$i]['quantity'] = ($temp_qty < 0) ? 0 : $temp_qty;

			if(!empty($channel_sku['custom_fields'])) {
 				foreach ($channel_sku['custom_fields'] as $key => $value) {
 					if (strcasecmp($key, 'Skus') == 0 || strcasecmp($key, 'Sku') == 0) {
 						// nested level e.g. Skus-grams
 						foreach ($channel_sku['custom_fields'][$key] as $k => $v) {
 							$skus[$i][$k] = $v;
 						}
 					}
 					else if (strcasecmp($key, 'Attributes') == 0) {
 						// nested level e.g. Attributes-grams
 						foreach ($channel_sku['custom_fields'][$key] as $k => $v) {
 							if ($k == "ShortDescription" || $k == 'short_description') {
								$k = 'short_description';

	 							$highlights = explode(PHP_EOL, $v);
	 							$shortDesc = "<ul>";

	 							foreach ($highlights as $highlight) {
	 								$shortDesc .= "<li>".$highlight."</li>";
	 							}

	 							$shortDesc .= "</ul>";
	 							$v = $shortDesc;
	 						}

 							$data['Product']['Attributes'][$k] = $v;
 						}
 					}
 					else {
 						// top level
 						$data['Product'][$key] = $value;
 					}
 				}
 			}

 			$productQuantity += $skus[$i]['quantity'];
			$i++;
		}

		$data['Product']['Available'] = $productQuantity;
		$data['Product']['Skus'] = $skus;
		return ['success' => true, 'product' => $data];
    }

    public function preparePrice(array $channel_sku) {
    	$data = array(
    			'price' 			=> $channel_sku['unit_price'],
    			'special_price' 	=> '',
    			'special_from_date' => '',
    			'special_to_date'	=> ''
    		);
    	
    	$price = $channel_sku['unit_price'];
		if(!empty($channel_sku['sale_price']) && $channel_sku['sale_price'] > 0)
		{
			if(strtotime($channel_sku['sale_start_date'])==false || strtotime($channel_sku['sale_end_date'])==false)
			{
				$message = 'Sales period must be specified with Listing price / Invalid sales period format.';
				$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
				return array('success' => false, 'error' => $this->error_data);
			}

			$now 				= Carbon::now($this->channel->timezone);
			$sales_start_date 	= Carbon::createFromFormat('Y-m-d',$channel_sku['sale_start_date'],$this->channel->timezone);
			$sales_end_date 	= Carbon::createFromFormat('Y-m-d',$channel_sku['sale_end_date'],$this->channel->timezone);

			if($sales_end_date->gte($sales_start_date))
			{
				$data['special_price'] 		= $channel_sku['sale_price'];
				$data['special_from_date'] 	= $sales_start_date->format('c');
				$data['special_to_date'] 	= $sales_end_date->format('c');
			}

			if($sales_end_date->gte($sales_start_date) && $now->gte($sales_start_date) && $now->lte($sales_end_date))
				$price = $channel_sku['sale_price'];
		}
		
		$this->livePriceSkus[$channel_sku['channel_sku_id']] = $price; 
		return $data;
    }

    public function getProductsQty(array $product_skus, $channel)
    {
        $params = array();
        $params['Action'] = 'GetProducts';
        $params['SkuSellerList'] = json_encode($product_skus);
        $return = array();
        $lazadaSc = $this->api($channel);
        $response = $lazadaSc('GET', $params, array());

        if(isset($response['success']) && !$response['success']) {
            return $response;
        }

        if(!isset($response['Body'])){
            $error = array();
            $error['error_desc'] = "Response Body Not Found.";
            $error['status_code'] = MpUtils::getStatusCode('DATA_ERROR');
            return MpUtils::errorResponse($error, __METHOD__, __LINE__);
        }
        if(!empty($response['Body']['Products'])){
            foreach($response['Body']['Products'] as $product){
                if(isset($product['Skus'][0])){
                    $return[] = array(
                        'chnlSku'       =>  $product['Skus'][0]['SellerSku'],
                        'stock_qty'     =>  $product['Skus'][0]['Available'],
                        'status'        =>  $product['Skus'][0]['Status']
                    );
                }
            }
        }

        return $return;
    }

    public function getShippingProviderDetail($request) {

    	$getShippingProviderDetail = array();
    	$params['Action'] = 'GetShipmentProviders';

		$sellerCenter = $this->api($this->channel);
		$response = $sellerCenter('GET', $params, array());

		if($response['success']==true){
			$details = $response['Body']['ShipmentProviders'];
			foreach ($details as $type => $datas) {
				if(is_array($datas)){
					if($datas['Cod']==0){
						$getShippingProviderDetail['name'] = $datas['Name'];
					}elseif ($datas['Cod']==1) {
						$getShippingProviderDetail['cod'] = $datas['Name'];
					}
				}else{
					if($type=="Name"){
						$getShippingProviderDetail['name'] = $datas;
					}
				}

			}
			$getShippingProviderDetail['success'] = true;
			return $getShippingProviderDetail;
		}else{
			$getShippingProviderDetail['success'] = false;
			return $getShippingProviderDetail;
		}

	}

    // END - PRODUCTS FUNCTIONS

    public function sendResponse($response) {}
}
