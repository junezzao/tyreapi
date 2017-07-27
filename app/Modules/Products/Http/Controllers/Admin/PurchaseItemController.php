<?php
namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Admin\AdminController;
use App\Modules\Products\Repositories\Contracts\PurchaseRepositoryContract as PurchaseRepository;
use App\Modules\Products\Repositories\Contracts\PurchaseItemRepositoryContract as PurchaseItemRepository;
use App\Repositories\Eloquent\BrandRepository as BrandRepo;
use App\Repositories\Eloquent\SKUOptionRepository as SKUOptionRepo;
use App\Repositories\Eloquent\SKUCombinationRepository as SKUCombinationRepo;
use App\Repositories\ProductRepository as ProductRepo;
use App\Repositories\SKURepository as SKURepo;
use App\Repositories\Eloquent\ProductTagRepository as TagRepo;

use App\Models\Admin\Purchase;
use App\Models\Admin\PurchaseItem;
use Bican\Roles\Models\Role;

use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;

class PurchaseItemController extends AdminController
{
	protected $purchaseRepo;
	protected $itemRepo;
	protected $productRepo;
	protected $skuRepo;
	protected $skuOptionRepo;
	protected $skuCombinationRepo;
	protected $tagRepo;
	protected $brandRepo;
	// protected $skuRepo;

    public function __construct(
    	Authorizer $authorizer,
    	PurchaseRepository $purchaseRepo,  
    	PurchaseItemRepository $itemRepo,  
    	ProductRepo $productRepo, 
    	SKURepo $skuRepo,
    	SKUOptionRepo $skuOptionRepo,
    	SKUCombinationRepo $skuCombinationRepo,
    	TagRepo $tagRepo,
    	BrandRepo $brandRepo)
    {
        $this->middleware('oauth');
        $this->productRepo = $productRepo;
        $this->skuRepo = $skuRepo;
        $this->skuOptionRepo = $skuOptionRepo;
        $this->skuCombinationRepo = $skuCombinationRepo;
        $this->tagRepo = $tagRepo;
        $this->purchaseRepo = $purchaseRepo;
        $this->itemRepo = $itemRepo;
    	$this->brandRepo = $brandRepo;
        $this->authorizer = $authorizer;
    }

	public function index($batch_id)
	{
		$items = $this->itemRepo->where('batch_id','=',$batch_id)->all();
		return response()->json(['items'=>$items]);
	}

	public function show($batch_id,$id)
	{
		$batch = $this->itemRepo->where('batch_id','=',$batch_id)->findOrFail($id);
		return response()->json($batch);
	}

	public function store($batch_id)
	{
		\DB::beginTransaction();
		$inputs = request()->all();
		$batch = $this->purchaseRepo->findOrFail($batch_id);
		
		$inputs['replenishment'] = $batch->replenishment;
		$inputs['merchant_id'] = $batch->merchant_id;
		
		$this->purchaseRepo->validate_items($inputs);
		$this->itemRepo->clear($batch_id);
		foreach($inputs['items'] as $item)
		{
			$item['batch_id'] = $batch->batch_id;
			$item['merchant_id'] = $batch->merchant_id;
			   	
		   	if($inputs['replenishment']!==1)
			{
				if (strcasecmp($item['product'], 'NEW') == 0 || $item['product'] === 0)
			 	{
				 	$productRepo = new $this->productRepo;
				 	$brand = $this->brandRepo->where('prefix','=',$item['prefix'])
				 				->where('merchant_id','=', $inputs['merchant_id'])->first();
				 	$item['brand_id'] = $brand->id?$brand->id:null;
                    $item['brand'] = $item['prefix'];
                    $item['merchant_id'] = $inputs['merchant_id'];
                    unset($item['prefix']);
				 	$product = $productRepo->create($item);

				 	//tags
					foreach($item['tags'] as $tag)
					{
						$tag = new $this->tagRepo;
						$tag->create(['value'=>$tag,'product_id'=>$product->id]);
					}
				}
			 	elseif($item['product'] != '')
				{
			 		$product = $this->productRepo->find( $item['product'] );
				}
				$item['product_id'] = $product->id;
				// create sku under the product
				$sku = $this->skuRepo->create($item);
				$item['sku_id'] = $sku->sku_id;
				foreach($item['option_name'] as $k => $v)
				{
					//options
					$optionRepo = new $this->skuOptionRepo;
					$option = $optionRepo->create(['option_name'=>$v,'option_value'=>$item['option_value'][$k]]);
					//combinations
					$combinationRepo = new $this->skuCombinationRepo;
					$combination = $combinationRepo->create(['option_id'=>$option->option_id,'sku_id'=>$sku->sku_id]);
				}
				
				$sku->update( ['hubwire_sku'=>!empty($item['client_sku'])?$item['client_sku']:$this->HWSKU($sku->sku_id)] );
			}
			
			$purchase_item = new $this->itemRepo(new PurchaseItem);
			$purchase_item->create($item);
		}
		$data = $this->purchaseRepo->with('items')->find($batch_id);
		\DB::commit();
        return response()->json($data);
	}

	public function update($batch_id, $id)
	{
		$inputs = request()->all();
		$batch = $this->purchaseRepo->findOrFail($batch_id);
		$update = $this->itemRepo->update($inputs, $id);
		$data = $this->itemRepo->find($id);
		if(!empty($inputs['productData']))
		{
			$productRepo = new ProductRepo;
			$product = $productRepo->massUpdate($inputs['productData'], $data->sku->product_id);
		}
		$data = $this->itemRepo->with('sku')->find($id);
		
		return response()->json($data);
	}

	public function destroy($batch_id,$id)
	{
		$item = $this->itemRepo->where('batch_id','=',$batch_id)->delete($id);
		return response()->json(['acknowledge'=>$item]);
	}
}
