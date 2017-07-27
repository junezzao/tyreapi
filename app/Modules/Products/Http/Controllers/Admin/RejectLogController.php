<?php
namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Admin\AdminController;
use App\Repositories\RejectLogRepository as RejectLogRepo;
use App\Modules\Channels\Repositories\Eloquent\ChannelRepository as ChannelRepo;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Criteria\Reject\ByChannel;
use App\Models\User;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\Merchant;
use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;
use Log;

class RejectLogController extends AdminController
{
	protected $authorizer;
	protected $rejectLogRepo;

	public function __construct(
    	Authorizer $authorizer,
    	RejectLogRepo $rejectLogRepo,
    	UserRepository $userRepo
    	)
    {
        $this->middleware('oauth');
        $this->userRepo = $userRepo;
        $this->rejectLogRepo = $rejectLogRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
		if(request()->get('channel_id')){
			$this->rejectLogRepo->pushCriteria(new ByChannel(request()->get('channel_id')));
		}
		$user = $this->userRepo->find($this->authorizer->getResourceOwnerId());
		$userRole = $user->getRoles();
		if($userRole[0]->level < 3){
			if(isset($user->merchant_id)){
				// if user is client admin or client user, filter by merchant.
				$channelRepo = new ChannelRepo(new Channel, new ChannelDetails, new Merchant);
				// get list of channel IDs under the merchant
				$channelIds = $channelRepo->byMerchantWithTrashed($user->merchant_id)->channels->pluck('id');
				$rejectLog = $this->rejectLogRepo->with('channel', 'user', 'sku')->whereIn('channel_id', $channelIds)->all();
			}else{
				$rejectLog = array();
			}
		}else{
			$rejectLog = $this->rejectLogRepo->with('channel', 'user', 'sku')->all();
		}
		return response()->json([
			'rejects'=> $rejectLog,
        ]);
	}

	public function store($product_id)
	{
		//
	}

	public function show($product_id, $id)
	{
		//
	}

	public function edit($product_id, $id)
	{
		//
	}

	public function update($product_id, $id)
	{
		//
	}

}