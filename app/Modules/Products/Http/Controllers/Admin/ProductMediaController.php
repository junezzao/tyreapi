<?php
namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Admin\AdminController;
use App\Repositories\Eloquent\ProductMediaRepository as MediaRepo;
use App\Repositories\ProductRepository as ProductRepo;

use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;
use Log;

class ProductMediaController extends AdminController
{
	protected $authorizer;
	protected $productRepo;
	protected $mediaRepo;
	public function __construct(
    	Authorizer $authorizer,
    	ProductRepo $productRepo,
    	MediaRepo $mediaRepo
    	)
    {
        $this->middleware('oauth');
        $this->productRepo = $productRepo;
        $this->mediaRepo = $mediaRepo;
        $this->authorizer = $authorizer;
    }

	public function index($product_id)
	{
		$tags = $this->mediaRepo->with('media')->where('product_id','=', $product_id)->all();
		return response()->json(['medias'=>$tags]);
	}

	public function store($product_id)
	{
		request()->merge(['product_id'=>$product_id]);
		$media = $this->mediaRepo->create(request()->all());
		return response()->json($media);
	}

	public function show($product_id, $id)
	{
		$media = $this->mediaRepo->with('media')->where('product_id','=',$product_id)->findOrFail($id);
		return response()->json($media);
	}

	public function edit($product_id, $id)
	{

	}

	public function update($product_id, $id)
	{
		request()->merge(['product_id'=>$product_id]);
		$media = $this->mediaRepo->update(request()->all(),$id);
		return response()->json($media);
	}

	public function updateImgOrder($product_id)
	{
		$img_sort_order = request()->get('img_sort_order',null);
		$imgIds = explode(',', $img_sort_order);
		
		\DB::beginTransaction();
		foreach($imgIds as $index => $id) {
			$id = (int) $id;
			if($id != '') {
				$mediaRepo = new $this->mediaRepo;
				$productMedia = $mediaRepo->where('product_id','=',$product_id)->findOrFail($id);
				$productMedia->sort_order = ($index + 1);
				$ok = $productMedia->save();
				if(!$ok) return response()->json(['acknowledge'=>false]);
			}
		}

		\DB::commit();

		return response()->json(['acknowledge'=>true]); 
	}

	public function destroy($product_id, $id)
	{
		$acknowledge = $this->mediaRepo->delete($id);
		return response()->json(['acknowledge'=>$acknowledge?true:false]); 	
	}
}