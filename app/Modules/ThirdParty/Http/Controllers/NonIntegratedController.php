<?php

namespace App\Modules\ThirdParty\Http\Controllers;

use GuzzleHttp\Exception\RequestException as RequestException;
use App\Modules\ThirdParty\Helpers\MarketplaceUtils as MpUtils;
use App\Modules\ThirdParty\Helpers\XmlUtils as XmlUtils;
use GuzzleHttp\Client as Guzzle;
use App\Modules\ThirdParty\Repositories\ElevenStreetRepo as ElevenStRepo;
use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use Monolog;
use Log;
use App\Modules\ThirdParty\Config;

/**
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */

class NonIntegratedController implements MarketplaceInterface
{
	protected $channel, $order;

	public function __construct() 
	{
	}

	public function initialize($channel)
	{
		$this->channel = $channel;
	}

	public function setOrder($order)
	{
		$this->order = $order;
	}

	public function readyToShip($input)
	{
		return array('success'=>true, 'tracking_no'=>$input['tracking_no']);
	}

	public function createProduct(array $product, $bulk = false) {}

    public function updateProduct(array $product, $bulk = false) {}

    public function updateVisibility(array $product, $bulk = false) {}

    public function deleteProduct(array $product, $bulk = false) {}

    public function createSku(array $sku, $bulk = false) {}

    public function updateSku(array $sku, $bulk = false) {}

    public function updateQuantity(array $sku, $bulk = false) {}

    public function deleteSku(array $sku, $bulk = false) {}

    public function updateImages(array $data) {}

    public function getOrders(array $filters) {}

    public function sendResponse($response) {}
}
