<?php 
namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\ProductMedia;
use App\Models\Admin\ProductThirdParty;
use DB;
use App\Models\BaseModel;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;

class ThirdPartySync extends BaseModel
{
	protected $table = 'third_party_sync';
	
	protected $fillable = [
		'channel_id',
		'channel_type_id',
		'ref_table',
		'ref_table_id',
		'action',
		'sync_type',
		'extra_info',
		'trigger_event',
		'request_id',
		'status',
		'retries',
		'remarks',
		'sent_time',
		'merchant_id',
		'created_at',
		'updated_at'
	];

	public function logs() {
		return $this->hasMany('App\Models\Admin\ThirdPartySyncLog', 'sync_id')->orderBy('sent_time', 'desc');
	}

    public static function updateSyncStatus($sync, $byProduct = false) {
    	$product_id = ($byProduct) ? $sync['product_id'] : 0;
    	$channel_id = ($byProduct) ? $sync['channel_id'] : $sync->channel_id;

    	$sync_status = '';

    	/*
    	 * Get product_id
    	 */
    	if (!$byProduct) {
    		if (strcasecmp($sync->ref_table, 'Product') == 0) {
    			$product_id = $sync->ref_table_id;
	    	}
	    	else if (strcasecmp($sync->ref_table, 'ChannelSKU') == 0) {
	    		$channel_sku = ChannelSKU::select('product_id')->where('channel_sku_id', '=', $sync->ref_table_id)->firstOrFail();
	    		$product_id = $channel_sku->product_id;
	    	}
	    	else if (strcasecmp($sync->ref_table, 'ProductMedia') == 0) {
	    		$product_media = ProductMedia::withTrashed()->select('product_id')->findOrFail($sync->ref_table_id);
	    		$product_id = $product_media->product_id;
	    	}
	    	else {
	    		return;
	    	}
    	}

    	/*
    	 * Check sync_status
    	 */
    	$third_party_sync = DB::select(DB::raw(
			"SELECT DISTINCT tps.status
				FROM third_party_sync tps
				LEFT JOIN channel_sku cs ON tps.ref_table_id = cs.channel_sku_id AND tps.ref_table = 'ChannelSKU'
				LEFT JOIN product_media pm ON tps.ref_table_id = pm.id AND tps.ref_table = 'ProductMedia'
				WHERE tps.channel_id = " . $channel_id .
				" AND (CASE tps.ref_table 
				WHEN 'Product' THEN tps.ref_table_id
				WHEN 'ChannelSKU' THEN cs.product_id
				WHEN 'ProductMedia' THEN pm.product_id END) = " . $product_id
		));

		$push_history = [];

		foreach ($third_party_sync as $tps) {
			array_push($push_history, $tps->status);
		}

		if (in_array("FAILED", $push_history) || in_array("ERROR", $push_history)) {
			$sync_status = "FAILED";
		}
		else if (in_array("QUEUED", $push_history) || in_array("PROCESSING", $push_history)
					|| in_array("PROCESS", $push_history) || in_array("SENT", $push_history))
		{
			$sync_status = "PROCESSING";
		}
		else {
			$product_third_party = ProductThirdParty::where('product_id', '=', $product_id)
													->where('channel_id', '=', $channel_id)
													->first();

			if(is_null($product_third_party) || $product_third_party->ref_id == null || $product_third_party->ref_id == 0) {
				$sync_status = "NEW";
			}
			else {
				$sync_status = "SUCCESS";
			}
		}

		/*
    	 * Update sync_status
    	 */
    	// ChannelSKU::where('product_id', '=', $product_id)
    	// 			->where('channel_id', '=', $sync->channel_id)
    	// 			->update(array('sync_status' => $sync_status));

    	$channelSkus = ChannelSKU::where('product_id', '=', $product_id)
    				->where('channel_id', '=', $channel_id)
    				->get();

    	foreach ($channelSkus as $channelSku) {
    		$channelSku->sync_status = $sync_status;
    		$channelSku->save();
    	}
    }

    public function getSentTimeAttribute($value)
    {	
    	if(is_null($value)){
    		return $value;
    	}
    	else{
    		try{
    			if(session()->has('user_timezone')){
    				$adminTz = session('user_timezone');
    			}else{
			        $userId = Authorizer::getResourceOwnerId();
			        $adminTz = User::where('id', '=', $userId)->value('timezone');
    				session(['user_timezone' => $adminTz]);
    			}
		        if($this->attributes['sent_time'] != '0000-00-00 00:00:00')
		        	return Helper::convertTimeToUserTimezone($value, $adminTz);
		        else
		        	return $value;
	        }catch(NoActiveAccessTokenException $e){
	            return $value;
	        }
    	}
    }
}
