<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\Brand;
use Bican\Roles\Models\Role;
use App\Repositories\Eloquent\BrandRepository;
use Activity;
use Illuminate\Support\Collection;
use Log;

class BrandsController extends AdminController
{
    protected $brandRepo;
    protected $authorizer;
    protected $userRepo;

    public function __construct(BrandRepository $brandRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->brandRepo = $brandRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
        $brands = $this->brandRepo->all(request()->all());
		return response()->json([
			'start'  => intval(\Input::get('start', 0)), 
            'limit'  => intval(\Input::get('limit', 30)), 
            'total'  => $this->brandRepo->count(),
            'brands' => $brands
        ]);
    }
    
    public function show($id)
    {
        $brand = $this->brandRepo->with('products')->findOrFail($id);
        return response()->json($brand);
    }

	public function store()
	{
		$brand = $this->brandRepo->create(\Input::except('access_token'));
		return response()->json($brand);
	}

    public function edit($id)
    {
    }

	public function update($id)
	{
		$brand = $this->brandRepo->update(\Input::except('access_token'), $id);
		return response()->json($brand);
	}

	public function destroy($id)
	{
        $success = $this->brandRepo->delete($id);
        return response()->json(['acknowledged'=>$success?true:false]);
	}

    public function getBrandsByMerchant($merchantId){
        // $this->brandRepo->pushCriteria(new ByMerchant($merchantId));

        return response()->json(
            $this->brandRepo->all(array('merchantId' => $merchantId))
        );
    }
}
