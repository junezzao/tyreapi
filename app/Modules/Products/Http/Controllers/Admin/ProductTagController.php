<?php
namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Admin\AdminController;
use App\Repositories\Eloquent\ProductTagRepository as TagRepo;
use App\Repositories\ProductRepository as ProductRepo;

use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;
use Log;

class ProductTagController extends AdminController
{
	protected $authorizer;
	protected $productRepo;
	protected $tagRepo;
	public function __construct(
    	Authorizer $authorizer,
    	ProductRepo $productRepo,
    	TagRepo $tagRepo
    	)
    {
        $this->middleware('oauth');
        $this->productRepo = $productRepo;
        $this->tagRepo = $tagRepo;
        $this->authorizer = $authorizer;
    }

	public function index($product_id)
	{
		$tags = $this->tagRepo->where('product_id','=', $product_id)->all();
		return response()->json(['tags'=>$tags]);
	}

	public function store($product_id)
	{
		request()->merge(['product_id'=>$product_id]);
		$tag = $this->tagRepo->create(request()->all());
		return response()->json($tag);
	}

	public function show($product_id, $id)
	{
		$tag = $this->tagRepo->where('product_id','=',$product_id)->findOrFail($id);
		return response()->json($tag);
	}

	public function edit($product_id, $id)
	{

	}

	public function update($product_id, $id)
	{
		request()->merge(['product_id'=>$product_id]);
		$tag = $this->tagRepo->update(request()->all(),$id);
		return response()->json($tag);
	}

	public function destroy($product_id, $id)
	{
		$acknowledge = $this->tagRepo->delete($id);
		return response()->json(['acknowledge'=>$acknowledge?true:false]); 	
	}
}