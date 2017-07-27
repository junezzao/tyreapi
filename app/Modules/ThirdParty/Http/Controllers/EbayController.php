<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
//use ThirdParty\Config as Config;
use GuzzleHttp\Client as Guzzle;

class EbayController extends ThirdPartyController
{
	/**
     * Api URL to connect for Ebay API
     * @var string
     */
    private $url;

	/**
     * App Id provided by Ebay Developer Account
     * @var string
     */
    private $app_id;

    /**
     * Dev Id provided by Ebay Developer Account
     * @var string
     */
    private $dev_id;

    /**
     * Cert Id provided by Ebay Developer Account
     * @var string
     */
    private $cert_id;

    /**
     * Auth Token provided by Ebay Developer Account
     * @var [type]
     */
    private $user_token; 

    /**
     * Ebay API version 
     * @var integer
     */
    private $compatability_level = 967;

    /**
     * Determine which country site we are making calls
     * List of Country ids can be found in thirdparty/config/ebay.php
     * 
     * @var integer
     */
    private $site_id; 
    
    private $error_data = array();

	protected $config_file;

	protected $categories_list = array();

	/**
	 * Set the valid credentials to interact with eBay API
	 * @param integer $site_id 		Determines which eBay site we are communicating with (207 for Malaysia, 0 for United States)
	 */
	public function __construct($site_id = 207)
	{
		$this->url = env('EBAY_API_URL');
		$this->dev_id = env('EBAY_DEV_ID');
		$this->app_id = env('EBAY_APP_ID');
		$this->cert_id = env('EBAY_CERT_ID');
		$this->user_token = env('EBAY_USER_TOKEN');
		$this->site_id = $site_id;
	}

    /**
	 * Get User under current eBay APP, this is purely to if eBay API is accessible with the login credentials
	 */
	public function getUser()
	{
		$xml_str = '<?xml version="1.0" encoding="utf-8"?>
				<GetUserRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
				<eBayAuthToken>'. $this->user_token .'</eBayAuthToken>
				</RequesterCredentials>
				</GetUserRequest>​';

		$response =  $this->guzzleRequest('post', 'GetUser', $xml_str);

		return $response;
	}

	public function createProduct($product)
	{
		$skus = '';
		foreach($product['skus'] as $sku) {
			$options = '';

			foreach($sku['options'] as $name => $value) {
				$options .= '<NameValueList>
					<Name>'. $name .'</Name>
					<Value>'. $value .'</Value>
				</NameValueList>';
			}

			$skus .= '<Variation>
				<SKU>'. $sku['sku'] .'</SKU>
				<StartPrice>'. $sku['price'] .'</StartPrice>
				<Quantity>'. $sku['quantity'] .'</Quantity>
				<VariationSpecifics>'. $options .'</VariationSpecifics>
			</Variation>';
		}

		$xml_str = '<?xml version="1.0" encoding="utf-8"?>
				<AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
				<eBayAuthToken>'. $this->user_token .'</eBayAuthToken>
				</RequesterCredentials>
				<Item>
					<Title>'. $product['product_name'] .'</Title>
					<Description>'. $product['product_description'] .'</Description>
					<PrimaryCategory>
						<CategoryID>'. $product['thirdparty_category'] .'</CategoryID>
					</PrimaryCategory>
					<CategoryMappingAllowed>true</CategoryMappingAllowed>
				    <ConditionID>1000</ConditionID>
				    <Country>US</Country>
				    <Currency>USD</Currency>
				    <DispatchTimeMax>3</DispatchTimeMax>
				    <ListingDuration>Days_7</ListingDuration>
				    <ListingType>FixedPriceItem</ListingType>
				    <PaymentMethods>PayPal</PaymentMethods>
				    <PayPalEmailAddress>megaonlinemerchant@gmail.com</PayPalEmailAddress>
				    <PictureDetails>
				        <PictureURL>http://i.ebayimg.sandbox.ebay.com/00/s/MTYwMFgxNjAw/z/zuYAAOSwAsNXX9hx/$_1.JPG?set_id=8800005007</PictureURL>
				    </PictureDetails>
				    <PostalCode>95125</PostalCode>
				    <ReturnPolicy>
				        <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>
				        <RefundOption>MoneyBack</RefundOption>
				        <ReturnsWithinOption>Days_30</ReturnsWithinOption>
				        <Description>If you are not satisfied, return the item for refund.</Description>
				        <ShippingCostPaidByOption>Buyer</ShippingCostPaidByOption>
				    </ReturnPolicy>
				    <ShippingDetails>
				        <ShippingType>Flat</ShippingType>
				        <ShippingServiceOptions>
				            <ShippingServicePriority>1</ShippingServicePriority>
				            <ShippingService>UPSGround</ShippingService>
				            <FreeShipping>true</FreeShipping>
				            <ShippingServiceAdditionalCost>0.00</ShippingServiceAdditionalCost>
				        </ShippingServiceOptions>
				    </ShippingDetails>
				    <Site>US</Site>
				    <ItemSpecifics>
				        <NameValueList>
				            <Name>Brand</Name>
				            <Value>'. $product['product_brand'] .'</Value>
				        </NameValueList>
				        <NameValueList>
				            <Name>Weight</Name>
				            <Value>'. $product['product_weight'] .'</Value>
				        </NameValueList>
				        <NameValueList>
				            <Name>Size Type</Name>
				            <Value>Regular</Value>
				        </NameValueList>
				        <NameValueList>
				            <Name>Style</Name>
				            <Value>Polo Shirt</Value>
				        </NameValueList>
				        <NameValueList>
				            <Name>Size (Women\'s)</Name>
				            <Value>S</Value>
				        </NameValueList>
				    </ItemSpecifics>
				    <Variations>
				    	<VariationSpecificsSet>
            				<NameValueList>
				                <Name>Color</Name>
				                <Value>Brown</Value>
				                <Value>White</Value>
				            </NameValueList>
        				</VariationSpecificsSet>
        			'. $skus .'</Variations>
				</Item>
				</AddFixedPriceItemRequest>​';

		$response =  $this->guzzleRequest('post', 'AddFixedPriceItem', $xml_str);

		return $response;
	}

	public function updateProduct($product)
	{
		$xml_str = '<?xml version="1.0" encoding="utf-8"?>
				<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
				<eBayAuthToken>'. $this->user_token .'</eBayAuthToken>
				</RequesterCredentials>
				<Item>
					<ItemID>'. $product['product_ref_id'] .'</ItemID>
					<Description>'. $product['product_description'] .'</Description>
				</Item>
				</ReviseFixedPriceItemRequest>​';

		$response =  $this->guzzleRequest('post', 'ReviseFixedPriceItem', $xml_str);

		return $response;
	}

	public function createSKU($product)
	{
		$skus = '';
		foreach($product['skus'] as $sku) {
			$options = '';

			foreach($sku['options'] as $name => $value) {
				$options .= '<NameValueList>
					<Name>'. $name .'</Name>
					<Value>'. $value .'</Value>
				</NameValueList>';
			}

			$skus .= '<Variation>
				<SKU>'. $sku['sku'] .'</SKU>
				<StartPrice>'. $sku['price'] .'</StartPrice>
				<Quantity>'. $sku['quantity'] .'</Quantity>
				<VariationSpecifics>'. $options .'</VariationSpecifics>
			</Variation>';
		}

		$xml_str = '<?xml version="1.0" encoding="utf-8"?>
				<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
				<eBayAuthToken>'. $this->user_token .'</eBayAuthToken>
				</RequesterCredentials>
				<Item>
					<ItemID>'. $product['product_ref_id'] .'</ItemID>
				    <Variations>
				    	<VariationSpecificsSet>
            				<NameValueList>
				                <Name>Color</Name>
				                <Value>Brown</Value>
				                <Value>White</Value>
				                <Value>Green</Value>
				            </NameValueList>
        				</VariationSpecificsSet>
				    '. $skus .'</Variations>
				</Item>
				</ReviseFixedPriceItemRequest>​';

		$response =  $this->guzzleRequest('post', 'ReviseFixedPriceItem', $xml_str);

		return $response;
	}

	public function getCategories($site)
	{
		$site_id = $this->getSiteId(strtoupper($site));

		if($site_id === false){
			return response('Error is retrieving categories for '.$site.'.');
		}

		$this->site_id = $site_id;

		$xml_str = '<?xml version="1.0" encoding="utf-8"?>
					<GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					<WarningLevel>High</WarningLevel>
					<RequesterCredentials>
					<eBayAuthToken>'. $this->user_token .'</eBayAuthToken>
					</RequesterCredentials>
					<WarningLevel>High</WarningLevel>
					<DetailLevel>ReturnAll</DetailLevel>
					</GetCategoriesRequest>';

		$response =  $this->guzzleRequest('post', 'getCategories', $xml_str);

		$xml = simplexml_load_string($response);
		
		foreach ($xml->CategoryArray->Category as $cat) {
			$this->categories_list[] = $this->xml2array($cat);	
		}
		
		return response()->json($this->categories_list);
		
	}

	function xml2array ( $xmlObject, $out = array () )
	{
	    foreach ( (array) $xmlObject as $index => $node )
	        $out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;

	    return $out;
	}

	function getSiteId ($global_id)
	{
		$map = array( 
				   "EBAY"    => 207,  // set default to Malaysia site	
				   "EBAY-US" => 0,
				   "EBAY-MY" => 207,
			   );

		if (isset($map[$global_id])) return $map[$global_id];

        return false;
	}

	private function guzzleRequest($request_type, $api_name, $body = array())
	{
		$guzzle = new Guzzle();
		$response = array();

		$res = $guzzle->request('POST', $this->url, [
		    'headers' => ['Content-Type'			 => 'application/xml',
					'Accept-charset'				 => 'utf-8',
					'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatability_level,
					'X-EBAY-API-DEV-NAME' 			 => $this->dev_id,
					'X-EBAY-API-APP-NAME'			 => $this->app_id,
					'X-EBAY-API-CERT-NAME'			 => $this->cert_id,
					'X-EBAY-API-SITEID'				 => $this->site_id,
					'X-EBAY-API-CALL-NAME'			 => $api_name,
					], 
		    'body' => $body,
		]);
		
		//if ($res->getHeader('Content-Type') == 'application/xml' && $res->getHeader('Content-Type') == 'text/xml') {
			return $res->getBody()->getContents();
			//return simplexml_load_string($res->getBody()->getContents()); 
		//} else {
		//	print($res->getHeader('Content-Type'));
		//}
		
		/*try
		{
			$request = $guzzle->createRequest($request_type, $this->url, [
				'headers' => [
					'Content-Type'					 => 'application/xml',
					'Accept-charset'				 => 'utf-8',
					'X-EBAY-API-COMPATIBILITY-LEVEL' => $this->compatability_level,
					'X-EBAY-API-DEV-NAME' 			 => $this->dev_id,
					'X-EBAY-API-APP-NAME'			 => $this->app_id,
					'X-EBAY-API-CERT-NAME'			 => $this->cert_id,
					'X-EBAY-API-SITEID'				 => $this->site_id,
					'X-EBAY-API-CALL-NAME'			 => $api_name
				],
				'body' => $body,
			]);

			$response = $guzzle->send($request);
			
			//handle error if xml is not found
			if ($response->getHeader('Content-Type')!='application/xml'&& $response->getHeader('Content-Type')!='text/xml')
			{
				$error_detail= (method_exists($response,'getBody') && method_exists($response->getBody(),'getContents'))?$response->getBody()->getContents():$response;
				$error = array();
				//$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
				$error['error_desc']= 'Request Failed , please check marketplace api settings . Response: '.$error_detail ;
				//return MpUtils::errorResponse($error, __METHOD__);
				return 'error';
			}

			$xml = $response->xml();

			$return['success'] = true;
			$return['xml'] = $xml;
			return $return;
		}
		catch(RequestException $e)
		{
			$error = array();
			$error['error_desc'] = "RequestException : ".$e->getMessage();
			//$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
			$request =$e->getRequest();
			$message ="RequestException : ".$e->getMessage();

			if($e->hasResponse())
			{
				$response= $e->getResponse();
			}else{
				$response = $e;
			}
			// return MpUtils::errorResponse($error, __METHOD__);
			return 'error';
		}
		catch(Exception $e)
		{
			$error = array();
			$error['error_desc'] = "Exception : ".$e->getMessage();
			//$error['status_code'] = MpUtils::getStatusCode('REQUEST_ERROR');
			$request =$e->getRequest();

			//return MpUtils::errorResponse($error, __METHOD__);
			return 'error';
		}*/
	}
}
