<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\Supplier;
use Bican\Roles\Models\Role;
use App\Repositories\Contracts\SupplierRepository;
use App\Repositories\Contracts\UserRepository;
use Activity;
use Illuminate\Support\Collection;
use App\Repositories\Criteria\Supplier\ByMerchant;

class SuppliersController extends AdminController
{
    protected $supplierRepo;
    protected $authorizer;
    protected $userRepo;

    public function __construct(SupplierRepository $supplierRepo, UserRepository $userRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->supplierRepo = $supplierRepo;
        $this->authorizer = $authorizer;
        $this->userRepo = $userRepo;
    }

	public function index()
	{
		$suppliers = $this->supplierRepo->all(request()->all());
		return response()->json([
			'start'		=> intval(request()->get('start', 0)), 
            'limit' 	=> intval(request()->get('limit', 30)), 
            'total' 	=> $this->supplierRepo->count(),
            'suppliers'	=> $suppliers
        ]);
    }
    
    public function show($id)
    {
        $supplier = $this->supplierRepo->with('merchant')->findOrFail($id);
        return response()->json($supplier);
    }

    public function showWithTrashed($id)
    {
    	$supplier = $this->supplierRepo->with('merchant')->withTrashFind($id);
        return response()->json($supplier);

    }

	public function store()
	{
		$supplier = $this->supplierRepo->create(request()->except('access_token'));
		Activity::log('Supplier '.$supplier->id.' was created', $this->authorizer->getResourceOwnerId());
		return response()->json($supplier);
	}

    public function edit($id)
    {
    }

	public function update($id)
	{
		$supplier = $this->supplierRepo->update(request()->except('access_token'), $id);
		Activity::log('Supplier ('.$id.' - '. $supplier->name.') was updated', $this->authorizer->getResourceOwnerId());
		return response()->json($supplier);
	}

	public function destroy($id)
	{
		$success = $this->supplierRepo->delete($id);
		if($success) Activity::log('Supplier ('.$id.')  was deleted', $this->authorizer->getResourceOwnerId());
		return response()->json(['acknowledged'=>$success?true:false]);
	}

	public function byMerchant($merchantId){
		//$this->supplierRepo->pushCriteria(new ByMerchant($merchantId));
		return response()->json($this->supplierRepo->all(array('merchant_id'=>$merchantId)));
	}
}
