<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\Merchant;
use Bican\Roles\Models\Role;
use App\Repositories\Contracts\MerchantRepository;
use App\Repositories\Contracts\UserRepository;
use Activity;
use Illuminate\Support\Collection;
use Log;
use Carbon\Carbon;

class MerchantsController extends AdminController
{
    protected $merchantRepo;
    protected $authorizer;
    protected $userRepo;

    public function __construct(MerchantRepository $merchantRepo, UserRepository $userRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->merchantRepo = $merchantRepo;
        $this->authorizer = $authorizer;
        $this->userRepo = $userRepo;
    }

	public function index()
	{
        $merchants = $this->merchantRepo->all(request()->all());
		return response()->json([
			'start'  	=> intval(\Input::get('start', 0)), 
            'limit' 	=> intval(\Input::get('limit', 30)), 
            'total' 	=> $this->merchantRepo->count(),
            'merchants' => $merchants
        ]);
    }

    public function indexWithTrashed()
    {
        $merchants = $this->merchantRepo->withTrashAll();
        return response()->json([
            'start'=> intval(\Input::get('start', 0)), 
            'limit' => intval(\Input::get('limit', 30)), 
            'total' => $this->merchantRepo->count(),
            'merchants'=>$merchants
        ]);
    }

    public function show($id)
    {
        $merchant = $this->merchantRepo->with('users','suppliers','brands','ae','channels')->findOrFail($id);
        return response()->json($merchant);
    }

    public function showWithTrashed($id)
    {
    	$merchant = $this->merchantRepo->withTrashFind($id);
        return response()->json($merchant);

    }

	public function store()
	{
		$merchant = $this->merchantRepo->create(\Input::except('access_token'));
		Activity::log('Merchant '.$merchant->id.' was created', $this->authorizer->getResourceOwnerId());
		return response()->json($merchant);
	}

    public function edit($id)
    {
    }

	public function update($id)
	{
		$merchant = $this->merchantRepo->update(\Input::except('access_token'), $id);
		Activity::log('Merchant ('.$id.' - '. $merchant->name.') was updated', $this->authorizer->getResourceOwnerId());
		return response()->json(['merchant'=>$merchant]);
	}

	public function destroy($id)
	{
		$success = $this->merchantRepo->delete($id);
		Activity::log('Merchant ('.$id.')  was deleted', $this->authorizer->getResourceOwnerId());
		return response()->json(['acknowledged'=>$success?true:false]);
	}

    public function getNewMerchantsByMonth()
    {
        $response = $this->merchantRepo->getNewMerchantsByMonth(\Input::get('month'));
        return response()->json(['merchants'=>$response]);
    }

    public function getActiveMerchants()
    {
        $response = $this->merchantRepo->getActiveMerchants(\Input::get('byDate'));
        return response()->json(['merchants'=>$response]);
    }

    public function getMerchantsByChannel($channelId)
    {
        $response = $this->merchantRepo->getMerchantsByChannel($channelId);
        return response()->json($response);
    }
}
