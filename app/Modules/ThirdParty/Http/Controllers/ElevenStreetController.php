<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;

use App\Http\Controllers\Admin\AdminController;
use App\Repositories\Eloquent\SyncRepository;
use App\Modules\ThirdParty\Repositories\ProductSyncRepo;
use App\Modules\ThirdParty\Repositories\ElevenStreetRepo as ElevenStRepo;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use App\Modules\ThirdParty\Config;

use App\Models\Admin\Channel;
use App\Models\Admin\ChannelType;
use App\Models\Admin\ThirdPartySync;

use Monolog;
use Response;
use Carbon\Carbon;

/**
 * The ElevenStreetController class contains various method for us to
 * interact with 11street API.
 *
 * @version   1.0
 * @author    Yuki <yuki@hubwire.com>
 */

class ElevenStreetController extends AdminController implements MarketplaceInterface
{
	/**
	 * Holds api key, url provided by 11street SOffice (Admin)
	 * @var array
	 */
	public $channel, $api_name, $customLog, $sync, $order;
	private $error_data = array();
	private $livePriceSkus = array();

	protected $api;

	public function __construct() {
		$this->api_name = get_class($this);

		$this->customLog = new Monolog\Logger('ElevenStreet Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/elevenstreet.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error 11Street';
		$this->error_data['File'] = __FILE__;

		set_exception_handler(array($this, 'exceptionHandler'));
	}

	public function exceptionHandler($e) {
		$this->error_data['Line'] = $e->getLine();
		$this->error_data['ErrorDescription'] = $e->getMessage();

		\Log::info('ElevenStreetController (' . $e->getLine() . ') >> Error: ' . $e->getMessage());
	}

	public function errorHandler($function, $line, $response, $message = '', $statusCode = '') {
		if(!empty($response)) {
			$this->error_data['response'] = $response;
		}

		$this->error_data['StatusCode'] = $statusCode;
		$this->error_data['Function'] = $function;
		$this->error_data['Line'] = $line;
		$this->error_data['ErrorDescription'] = $message;

		if(!empty($this->sync)) {
			$this->sync->status = 'FAILED';
			$this->sync->remarks = json_encode($message);
			$this->sync->save();
			$this->error_data['Sync'] = $this->sync->toArray();
		}

		$this->ErrorAlert($this->error_data);
	}

	/**
	 * Set the valid credentials to interact with 11street API
	 * @param array $channel_details
	 * 		$params = [
	 *			'api_key'	 => (string) API Key provided by 11street Soffice > Member Details > Seller Details
	 *			'api_secret' => (string) API URL to which call has to be made. Could be different for different regions
	 * 		]
	 */
	public function initialize(Channel $channel, ThirdPartySync $sync = null)
	{
		$this->api['key'] = $channel->channel_detail->api_key;
		$this->api['url'] = $channel->channel_detail->api_secret;
		$this->channel = $channel;
		$this->sync = is_null($sync) ? null : $sync;
	}

	private function guzzleRequest($request_type, $uri, $body = '')
	{
		$response = array();
		try
		{
			$url = $this->api['url'] . $uri;
			$logInfo = "";
			$logInfo .= !is_null($this->sync) ? ("Sync ID " . $this->sync->id . ", Event: " . $this->sync->action . " | ") : "";
			$logInfo .= "Request Body/Query: ";
			$this->customLog->addInfo($logInfo . $body);

			$guzzle = new Client();
			$requestHeaders = array(
				'Content-Type'	 => 'application/xml',
				'Accept-charset' => 'utf-8',
				'openapikey'	 => $this->api['key']
			);

			$request = new GuzzleRequest($request_type, $url, $requestHeaders, $body);
			\Log::info(print_r($body, true));
			$response = $guzzle->send($request);

			if ($response->getHeader('Content-Type')[0] != 'application/xml' && $response->getHeader('Content-Type')[0] != 'text/xml')
			{
				$error_detail = method_exists($response, 'getBody') && method_exists($response->getBody(), 'getContents') ? $response->getBody()->getContents() : $response;
				$message = 'Request Failed , please check marketplace api settings . Response: '. $error_detail;
				$this->errorHandler(__FUNCTION__, __LINE__, $error_detail, $message, MpUtils::getStatusCode('REQUEST_ERROR'));

				return false;
			}

			$xml = simplexml_load_string($response->getBody()->getContents());

			return $xml;
		}
		catch (RequestException $e)
		{
			$message = "Error send request. " . $e->getMessage() . " Request URL: " . $url;

			$response = $e->getResponse();
			$this->error_data['header'] = !empty($response) ? $response->getHeaders() : '';

			$this->errorHandler(__FUNCTION__, $e->getLine(), $response, $message, MpUtils::getStatusCode('REQUEST_ERROR'));

			if ($e->hasResponse()) {
				$this->customLog->addError(print_r($e->getResponse(), true));
		    }

			return false;
		}
		catch (Exception $e)
		{
			$message = "Error send request. Unknown error. Request URL: " . $url;
			$message .= ' Error: ' . $e->getMessage() . ' at line ' . $e->getLine();

			$this->customLog->addError($message);

		    $this->errorHandler(__FUNCTION__, $e->getLine(), (!empty($response) ? $response : null), $message, MpUtils::getStatusCode('REQUEST_ERROR'));

			return false;
		}
	}

	public function setOrder($order) {
		$this->order = $order;
	}

	public function getOrders(array $filter)
	{
		$response = ElevenStRepo::prepareOrderRequest(MpUtils::getTypeCode('GET_ORDER'), $filter);
		if(!$response['success'])
		{
			return $response;
		}

		$param = $response['param'];
		$requestUri = array();
		$requestUri['Paid'] = 'ordservices/complete';
		$requestUri['Packing'] = 'ordservices/packaging';

		$return = array();
		foreach ($requestUri as $status => $uri)
		{
			$response = $this->guzzleRequest('get', "{$uri}/{$param['startTime']}/{$param['endTime']}");
			if($response === false)
			{
				$return[] = $response;
				continue;
			}

			$xml = $response;
			if(isset($xml->result_code) && $xml->result_code!='200')
			{
				$error = array();
				$error['error_desc'] = !empty($xml->message) ? $xml->message : $xml->result_text;
				$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
				$return[] =  MpUtils::errorResponse($error, __METHOD__, __LINE__);
			}
			else
			{
				//process output
				$channel = Channel::where('id', $this->channel->id)->first();
				$orders = ElevenStRepo::processOrder($xml, $channel);
				if(isset($orders['success']) && $orders['success'] == false) {
					$return[] = $orders;
				}
				else {
					$return = array_merge($return, $orders);
				}
			}
		}
		return $return;
	}

	public function getSingleOrder($order_code)
	{
		$return = array();
		if($order_code != '') {
			$response = $this->guzzleRequest('get',"/ordservices/complete/".$order_code);

			if($response['success'] === false )
			{
				$return['reponse'] = $response;
			}
			$xml = $response;


			if(isset($xml->result_code)&&$xml->result_code != '200')
			{
				$error = array();
				$error['error_desc'] = "11street getOrder request returns error_code ".$xml->result_code;
				$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
				$return['response'] =  MpUtils::errorResponse($error, __METHOD__, __LINE__);
			} else {
				//process output
				$channel = Channel::where('id', $this->channel->id)->first();
				$return['response'] = ElevenStRepo::processOrder($xml, $channel);
			}
		}
		return $return;
	}

	public function readyToShip($input)
	{
		$orderTpExtra = json_decode($this->order->tp_extra, true);

		//check if order is cancelled in 11Street
		$response = $this->getSingleOrder($this->order->tp_order_id);

		if (!empty($response['response']['success']) && !$response['response']['success']) {
			return ['success' => false, 'message' => 'Error checking order status in 11Street.'];
		}

		$order = $response['response'][$this->order->tp_order_id]['order'];
		$orderExtra = json_decode($order->tp_extra, true);

		$invalidOrderStatusedForRTS = ['701', 'B01', 'C01'];
		if (in_array($orderExtra['sale_status'], $invalidOrderStatusedForRTS)) {
			return ['success' => false, 'message' => 'Order has been cancelled or the cancellation has been requested in 11Street.'];
		}
		//end checking

		$shippingNo = isset($orderTpExtra['shipping_no']) ? $orderTpExtra['shipping_no'] : '';
		$saleStatus = Config::get('marketplace.sales_status.Shipped');
		$extra_info = json_decode($this->channel->channel_detail->extra_info, true);
		$details = array(
			'sent_date'		  => date(Config::get('marketplace.std_date_format')),
			'shipping_method' => Config::get('elevenSt.default.shipping_method'),
			'courier_company' => $extra_info['shipping_provider'],
			'tracking_no'	  => $input['tracking_no'],
			'shipping_no'	  => $shippingNo
		);
		$response = $this->setSalesStatus($saleStatus, $details, $input['tracking_no']);
		$response2 = $this->receiveReadyToShipResponse($response);

		return $response2;
	}

	public function setSalesStatus($status, $details, $tracking_no)
	{
		$configStatus = MpUtils::getSalesStatus();
		$status = ucfirst($status);

		$response = ElevenStRepo::verifyOrderRequest($status, $details);
		if(!$response["success"])
		{
			return $response;
		}

		switch($status)
		{
			case $configStatus['Packing']:
				$request_url = "ordservices/reqpackaging/";
				break;
			case $configStatus['Shipped']:
				$request_url = "ordservices/reqdelivery/";
				break;
			case $configStatus['Cancelled']:
				$request_url = "claimservice/reqrejectorder/";
				break;
		}

		$response = ElevenStRepo::prepareOrderStatusRequest($status, $details);
		if(!$response['success'])
		{
			$response['request_url'] = $request_url;
			return $response;
		}

		$this->customLog->addInfo('ReadyToShip sending Guzzle request... '. $request_url.$response['queryStr']);
		$response = $this->guzzleRequest('get', $request_url.$response['queryStr']);
		$this->customLog->addInfo('ReadyToShip response... '. print_r($response, true));
		if($response === false)
		{
			$response['request_url'] = $request_url.$response['queryStr'];
			return $response;
		}

		$xml = $response;
		if(isset($xml->result_code) && $xml->result_code!='0')
		{
			$error['success'] = false;
			$error['message'] = (string) $xml->result_text;
			return $error;
		}
		else
		{
			$result['success'] = true;
			$result['tracking_no'] = $tracking_no;
			return $result;
		}
	}

	public function receiveReadyToShipResponse($response)
	{
		$status = array();
		if(!$response['success']){
			$errorLog = 'Set status to readyToShip failed for order #'. $this->order->id .'. ';
			if(!empty($response['request_url']))
				$errorLog .= 'Request: <' . $response['request_url'] . ' > | ';
			$errorLog .= 'Response: <' . print_r($response, true) . '>. ';
			$errorLog .= 'Remark: Set sale status failed.';
			$this->customLog->addError($errorLog);
		}
		return $response;
	}

	/**
	 * Get Categories
	 * @return array $return
	 */
	public function getCategories()
	{
		$response = $this->guzzleRequest('get', 'cateservice/category');
		if ($response === false) {
			return $response;
		}

		$response = ElevenStRepo::processCategories($response);

		return $response;
	}

	public function createProduct(array $data, $bulk = false) {
		$product = $this->prepareProduct($data);
		if (is_array($product) && isset($product["success"]) && $product["success"] === false) {
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$response = ElevenStRepo::verifyProduct($product, MpUtils::getTypeCode('CREATE_PRODUCT'));
		if ($response["success"] === false) {
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $response["error"], MpUtils::getStatusCode('VALIDATION_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$response = ElevenStRepo::prepareProduct($product);
		if ($response["success"] === false) {
			$this->errorHandler(__FUNCTION__, __LINE__, $response, 'Prepare product failed.', MpUtils::getStatusCode('VALIDATION_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$preparedProduct = $response['product'];
		$mp_product = ElevenStRepo::convertToArray($preparedProduct);
		$xml_str = XmlUtils::arrayToXml($mp_product, 'Product');
		$response =  $this->guzzleRequest('post', "prodservices/product", $xml_str);

		if ($response === false){
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		//process output
		if ($response->resultCode != '200') {
			$message = "Create product API returns " . $response->resultCode . "-" . $response->message;
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $message, MpUtils::getStatusCode('REQUEST_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('REQUEST_ERROR'));
		}

		$mp_product_id = (string) $response->productNo;

		$productThirdParty = array(
			'ref_id' => $mp_product_id,
			'channel_id' => $this->channel->id,
			'third_party_name' => 'Elevenstreet',
			'product_id' => $this->sync->ref_table_id
		);

		ProductSyncRepo::storeProductThirdPartyInfo($productThirdParty);

		$skusResponses = $this->getProductSkus($mp_product_id, 'name', false);

		if ($skusResponses === false) {
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('REQUEST_ERROR'));
		}

		$skus = $skusResponses[$mp_product_id]['skus'];

		foreach ($product->skus as $sku) {
			$skuData = array(
				'hubwire_sku'	=> $sku->sku_id, // the hubwire_sku in stored in sku_id index of the object
				'merchant_id'	=> $data['merchant_id'],
				'channel_id'	=> $this->channel->id,
				'ref_id'		=> $skus[$sku->name]['sku_ref_id'],
				'product_id'	=> $data['id']
			);

			$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($skuData);

			if(!$storeResponse){
				$message = 'Expected Response Not Match With Hubwire Model: SKU ' . $sku->sku_id . ' | data sent: ' . json_encode($mp_product);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message, MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}
		}

		foreach($data['images'] as $media) {
			$imageInfo = array(
				'media_id'			=> $media['id'],
				'ref_id'			=> $media['id'],
				'channel_id'		=> $this->channel->id,
				'third_party_name'	=> 'Elevenstreet'
			);

			ProductSyncRepo::storeMediaThirdPartyInfo($imageInfo);
		}

		$this->sync->remarks = json_encode($response);
		$this->sync->status = 'SUCCESS';
		$this->sync->save();

		ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
	}


    public function updateProduct(array $data, $bulk = false) {
    	$product = $this->prepareProduct($data);
    	if (is_array($product) && isset($product["success"]) && $product["success"] === false) {
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$response = ElevenStRepo::verifyProduct($product, MpUtils::getTypeCode('UPDATE_PRODUCT'));
		if ($response["success"] === false) {
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $response["error"], MpUtils::getStatusCode('VALIDATION_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$response = ElevenStRepo::prepareProduct($product);
		if ($response["success"] === false) {
			$this->errorHandler(__FUNCTION__, __LINE__, $response, 'Prepare product failed.', MpUtils::getStatusCode('VALIDATION_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$preparedProduct = $response['product'];
		$mp_product = ElevenStRepo::convertToArray($preparedProduct);
		$xml_str = XmlUtils::arrayToXml($mp_product, 'Product');
		$response =  $this->guzzleRequest('post',"prodservices/product/" . $product->product_ref_id, $xml_str);

		if ($response === false) {
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		//process output
		if ($response->resultCode != '200') {
			$message = "Create product API returns " . $response->resultCode . "-" . $response->message;
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $message, MpUtils::getStatusCode('REQUEST_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('REQUEST_ERROR'));
		}

		$mp_product_id = (string) $response->productNo;

		$skusResponses = $this->getProductSkus($mp_product_id, 'name', false);

		if ($skusResponses === false) {
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('REQUEST_ERROR'));
		}

		$skus = $skusResponses[$mp_product_id]['skus'];

		foreach ($product->skus as $sku) {
			$skuData = array(
				'hubwire_sku'	=> $sku->sku_id, // the hubwire_sku in stored in sku_id index of the object
				'merchant_id'	=> $data['merchant_id'],
				'channel_id'	=> $this->channel->id,
				'ref_id'		=> $skus[$sku->name]['sku_ref_id'],
				'product_id'	=> $data['id']
			);

			$storeResponse = ProductSyncRepo::storeSkuThirdPartyInfo($skuData);

			if(!$storeResponse){
				$message = 'Expected Response Not Match With Hubwire Model: SKU ' . $sku->sku_id . ' | data sent: ' . json_encode($mp_product);
				$this->errorHandler(__FUNCTION__, __LINE__, $response, $message, MpUtils::getStatusCode('MARKETPLACE_ERROR'));
				return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
			}
		}

		foreach($data['images'] as $media) {
			$imageInfo = array(
				'media_id'			=> $media['id'],
				'ref_id'			=> $media['id'],
				'channel_id'		=> $this->channel->id,
				'third_party_name'	=> 'Elevenstreet'
			);

			ProductSyncRepo::storeMediaThirdPartyInfo($imageInfo);
		}

		$this->sync->remarks = json_encode($response);
		$this->sync->status = 'SUCCESS';
		$this->sync->save();

		ProductSyncRepo::updateSkuLivePrice($this->livePriceSkus);
    }

    public function updatePrice(array $sku, $bulk = false) {
    	$this->updateProduct($sku, $bulk);
    }

    public function updateVisibility(array $product, $bulk = false) {}

    public function deleteProduct(array $product, $bulk = false) {}

    public function createSku(array $sku, $bulk = false) {
    	$this->updateProduct($sku, $bulk);
    }

    public function updateSku(array $sku, $bulk = false) {
    	$this->updateProduct($sku, $bulk);
    }

    public function deleteSku(array $sku, $bulk = false) {}

    public function updateImages(array $data) {
    	return $this->updateProduct($data);
    }

    private function prepareProduct(array $prod, $isCreate = true) {
		$product['product_ref_id'] = $prod['product_ref'];
		$product['product_code'] = $prod['id'];
		$product['product_name'] = $prod['name'];
		$product['product_brand'] = $prod['brand'];
		$product['thirdparty_category'] = !empty($prod['category']) ? $prod['category'] : $prod['default_category'];

		$media_array = array();
		foreach($prod['images'] as $image){
			$media_array[] = $image['path'] . (!empty($image['ext']) ? $image['ext'] : '.jpg');
		}

		$product['product_images'] = $media_array;
		$product['product_description'] = $prod['description'];

        $sku_array = array();
        $weight = array();
        $price = array();
        $retail_price = array();

        $has_discount = false;
        $g_sales_start_date = null;
		$g_sales_end_date = null;

        foreach ($prod['sku_in_channel'] as $k => $v) {
        	$name = $v['options']['Colour'] . '-';
			foreach ($v['options'] as $optionType => $optionValue) {
				if ($optionType != 'Colour') {
					$name .= $optionValue . "-";
				}
			}
			$name = rtrim($name, '-');

			$sku_array[$k] = array(
        		'quantity'		=> $v['quantity'],
        		'sku_id'		=> $v['hubwire_sku'],
        		'active'		=> $v['is_active'] ? 1 : 0,
        		'name'			=> $name,
        		'add_weight'	=> round(($v['weight'] / 1000), 3), // convert to KG
        	);

			if (isset($v['sale_price']) && $v['sale_price'] > 0)
			{
				if(strtotime($v['sale_start_date'])==false || strtotime($v['sale_end_date'])==false)
				{
					$message = 'Sales period must be specified with Listing price / Invalid sales period format.';
					$this->errorHandler(__FUNCTION__, __LINE__, null, $message);
					return array('success' => false, 'error' => $this->error_data);
				}

				$now 				= Carbon::now($this->channel->timezone);
				$sales_start_date 	= Carbon::createFromFormat('Y-m-d',$v['sale_start_date'],$this->channel->timezone);
				$sales_end_date 	= Carbon::createFromFormat('Y-m-d',$v['sale_end_date'],$this->channel->timezone);
				
				if($sales_end_date->gte($sales_start_date) && $now->gte($sales_start_date) && $now->lte($sales_end_date))
				{
					$has_discount = true;
					$sku_array[$k]['add_price'] = $v['sale_price'];
					$g_sales_start_date = $sales_start_date;
					$g_sales_end_date = $sales_end_date;
				}
				else
				{
					$sku_array[$k]['add_price'] = $v['unit_price'];
				}
			}
			else {
				$sku_array[$k]['add_price'] = $v['unit_price'];
			}

			$weight[] = $sku_array[$k]['add_weight'];

			// 11street only considers price for sku which are more than 1 in quantity and active (valid)
			$active = ($sku_array[$k]['active']);
			if ($active) {
				$price[]  = $sku_array[$k]['add_price'];
			}
			$retail_price[] = $v['unit_price'];

			// custom fields
			if(!empty($v['custom_fields'])) {
 				foreach ($v['custom_fields'] as $key => $value) {
 					// productSelOption == sku
 					$nestedFieldNames = array("productWrtOption", "productSelOption", "ProductDlvTariff", "productDlvPrmt"); // "ProductComponent"

 					if (in_array($key, $nestedFieldNames)) {
 						// nested level e.g. productWrtOption-{option_name}
 						if ($key == 'productSelOption') {
 							foreach ($v['custom_fields'][$key] as $nestedField => $nestedValue) {
								$sku_array[$k][$nestedField] = $nestedValue;
	 						}
 						}
 						else if ($key == 'ProductDlvTariff') {
 							if (empty($product[$key])) {
								$product[$key][0]['dlvDstnCd'] = '01';
								$product[$key][1]['dlvDstnCd'] = '02';
								$product[$key][2]['dlvDstnCd'] = '03';
							}

							foreach ($v['custom_fields'][$key] as $nestedField => $nestedValue) {
								$str = explode('-', $nestedField);
								$area = isset($str[0]) ? $str[0] : '';
								$field = isset($str[1]) ? $str[1] : '';

								if($area == 'WestMalaysia') $index = 0;
								if($area == 'Sabah') $index = 1;
								if($area == 'Sarawak') $index = 2;

								$product[$key][$index][$field] = $nestedValue;
	 						}
 						}
 						else if ($key == 'productWrtOption' || $key == 'productDlvPrmt') {
 							$product[$key] = $v['custom_fields'][$key];
 						}
 						// else if ($key == 'ProductComponent') {
							// foreach ($v['custom_fields'][$key] as $nestedField => $nestedValue) {
							// 	$product[$key][$k][$nestedField] = $nestedValue;
 						// 	}
 						// }
 					}
 					else {
 						// top level
 						$product[$key] = $value;
 					}
 				}
 			}
		}

		//Sort weight
		sort($weight, SORT_NUMERIC);
		$product['product_weight'] = $weight[0];

		sort($price, SORT_NUMERIC);
		sort($retail_price, SORT_NUMERIC);

		$highest_price = empty($retail_price) ? 0 : $retail_price[count($retail_price) - 1];
		$lowest_price = empty($price) ? 0 : $price[0];

		foreach ($sku_array as $k => $v){
			if($lowest_price <= 0) $lowest_price = $v['add_price'];

			// Set Add Price
			$price_value = $v['add_price'] - $lowest_price;
			$this->livePriceSkus[$k] = $v['add_price'];
			$v['add_price'] = isset($sku_array[$k]['optPrc']) ? $sku_array[$k]['optPrc'] : $price_value;
			
			//Set Add Weight
			$weight_value = $v['add_weight'] - $product['product_weight'];
			$v['add_weight'] = isset($sku_array[$k]['optWght']) ? $sku_array[$k]['optWght'] : $weight_value;

			// Type cast to Object
			$product['skus'][] = $v;
		}

		//discount
		if ($has_discount) {
			$product['cupnUseLmtDyYn'] 	= 'Y';
			$product['cupnIssStartDy'] 	= $g_sales_start_date->format('d/m/Y');
			$product['cupnIssEndDy'] 	= $g_sales_end_date->format('d/m/Y');

			$product['cuponcheck'] = 'Y';
			$product['cupnDscMthdCd'] = '01';

			$product['dscAmtPercnt'] = $highest_price - $lowest_price;
			$product['product_price'] = $highest_price;
		}
		else {
			$product['product_price'] = $lowest_price;
		}

		$product['option_title'] = 'Option';

		return json_decode(json_encode($product));
    }

    public function getProductSkus($product_ref_id, $sku_id_field = '', $bulk = false)
	{
		$arr['ProductStock']['prdNo'] = $product_ref_id;

		//convert to xml
		$i_xml_str = XmlUtils::arrayToXml($arr, 'ProductStocks');
		$response = $this->guzzleRequest('post', "prodmarketservice/prodmarket/stocks", $i_xml_str);

		if($response === false) {
			return $response;
		}

		return ElevenStRepo::processSKU($response, $sku_id_field);
	}

    public function updateQuantity(array $sku, $bulk = false) {
		$data = array(
			'product_ref_id'	=> $sku['product_ref'],
			'quantity' 			=> ($sku['quantity'] < 0) ? 0 : $sku['quantity'],
			'sku_ref_id' 		=> $sku['channel_sku_ref']
		);

		$data = json_decode(json_encode($data));

		$response = ElevenStRepo::verifyProduct($data, MpUtils::getTypeCode('STOCK_QTY_UPDATE'));
		if ($response["success"] === false) {
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $response["error"], MpUtils::getStatusCode('VALIDATION_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('VALIDATION_ERROR'));
		}

		$mp_product = array(
			'prdNo'		=> $data->product_ref_id,
			'stckQty'	=> ($data->quantity < 0) ? 0 : $data->quantity
		);

		$xml_str = XmlUtils::arrayToXml($mp_product, 'ProductStock');
		$response =  $this->guzzleRequest('put', "prodservices/stockqty/" . $data->sku_ref_id, $xml_str);

        if ($response === false){
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('MARKETPLACE_ERROR'));
		}

		//process output
		if ($response->resultCode != '200') {
			$message = "Create product API returns " . $response->resultCode . "-" . $response->message;
			$this->errorHandler(__FUNCTION__, __LINE__, $response, $message, MpUtils::getStatusCode('REQUEST_ERROR'));
			return Response::JSON(array('success' => false, 'error' => $this->error_data), MpUtils::getStatusCode('REQUEST_ERROR'));
		}

		$this->sync->remarks = json_encode($response);
		$this->sync->status = 'SUCCESS';
		$this->sync->save();
    }

	public function sendResponse($response) {}

	public function getProductQty($tp_prod_id)
	{
		$return = array();
		if($tp_prod_id != '') {
			$response = $this->guzzleRequest('get',"/prodmarketservice/prodmarket/stck/".$tp_prod_id);

			if($response['success'] === false )
			{
				$return['reponse'] = $response;
			}
			$xml = $response;

			if(isset($xml->result_code)&&$xml->result_code != '200')
			{
				$error = array();
				$error['error_desc'] = "11street Get Single ProductStockNo request returns error_code ".$xml->result_code;
				$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
				$return['response'] =  MpUtils::errorResponse($error, __METHOD__, __LINE__);
			} else {
				//get product stock from 11st response
				foreach($xml->xpath('//ProductStock') as $prodInfo)
				{
					$prodInfo = (array) $prodInfo;
					$return[] = array(
						'chnl_sku_ref_id'	=>	$prodInfo['prdStckNo'],
						'product_ref_id'	=>	$prodInfo['prdNo'],
						'stock_qty'			=>	$prodInfo['stckQty']
					);
				}
			}
		}

		return $return;
	}

	public function getProductsQty(array $tp_prod_ids)
	{
		$return = array();
		$postData = array();
		$postXMLBody = '';

		// \Log::info(print_r($tp_prod_ids, true));

		foreach($tp_prod_ids as $prodId){
			$postData['ProductStock'][] = array('prdNo'=>$prodId);
		}

		$postXMLBody = XmlUtils::arrayToXml($postData, 'ProductStocks');

		// \Log::info(print_r($postXMLBody, true));

		$response = $this->guzzleRequest('post', '/prodmarketservice/prodmarket/stocks', $postXMLBody);

		if($response['success'] === false )
		{
			$return['reponse'] = $response;
		}

		$xml = $response;

		// \Log::info(print_r($xml, true));

		if(isset($xml->result_code)&&$xml->result_code != '200')
		{
			$error = array();
			$error['error_desc'] = "11street Get Multiple ProductStockNo request returns error_code ".$xml->result_code;
			$error['status_code'] = MpUtils::getStatusCode('MARKETPLACE_ERROR');
			$return['response'] =  MpUtils::errorResponse($error, __METHOD__, __LINE__);
		} else {
			//get product stock from 11st response
			foreach($xml->xpath('//ProductStock') as $prodInfo){
				$prodInfo = (array) $prodInfo;
				$return[] = array(
					'chnl_sku_ref_id'	=>	$prodInfo['prdStckNo'],
					'product_ref_id'	=>	$prodInfo['prdNo'],
					'stock_qty'			=>	$prodInfo['stckQty']
				);
			}
		}

		return $return;
	}
}
