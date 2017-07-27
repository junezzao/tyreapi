<?php
namespace App\Modules\ThirdParty\Http\Controllers;

use App\Modules\ThirdParty\Repositories\Contracts\MarketplaceInterface;
use Monolog;
use Carbon\Carbon;

/**
 * @version   1.0
 * @author    Jun Ng <jun@hubwire.com>
 */
class BearInBagController extends StorefrontVendorController
{
	public $channel, $api_name, $__api, $customLog, $sync;
	private $error_data = array();

	public function __construct(){
		$this->api_name = 'BearInBag';

		$this->customLog = new Monolog\Logger('BearInBag Log');
		$this->customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/bear_in_bag.log', Monolog\Logger::INFO));

		$this->error_data['subject'] = 'Error BearInBag';
		$this->error_data['File'] = __FILE__;
	}
}
