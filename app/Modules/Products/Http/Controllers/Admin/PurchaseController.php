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
use App\Repositories\Eloquent\CategoryRepository as CategoryRepo;
use App\Models\Admin\ProductTag;
use App\Models\Admin\Purchase;
use App\Models\Admin\PurchaseItem;
use App\Models\Admin\Brand;
use App\Models\Admin\Product;

use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;

class PurchaseController extends AdminController
{
	protected $authorizer;
	protected $purchaseRepo;
	protected $productRepo;
	protected $skuRepo;
	protected $skuOptionRepo;
	protected $skuCombinationRepo;
	protected $tagRepo;
	protected $brandRepo;
	// protected $skuRepo;
	protected $categoryRepo;

    public function __construct(
    	Authorizer $authorizer,
    	PurchaseRepository $purchaseRepo,
    	PurchaseItemRepository $itemRepo,
    	ProductRepo $productRepo,
    	SKURepo $skuRepo,
    	SKUOptionRepo $skuOptionRepo,
    	SKUCombinationRepo $skuCombinationRepo,
    	TagRepo $tagRepo,
    	BrandRepo $brandRepo,
    	CategoryRepo $categoryRepo)
    {
        $this->middleware('oauth');
        $this->productRepo = $productRepo;
        $this->itemRepo = $itemRepo;
        $this->skuRepo = $skuRepo;
        $this->skuOptionRepo = $skuOptionRepo;
        $this->skuCombinationRepo = $skuCombinationRepo;
        $this->tagRepo = $tagRepo;
        $this->purchaseRepo = $purchaseRepo;
        $this->brandRepo = $brandRepo;
        $this->categoryRepo = $categoryRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
        // items causing delays
		$batches = $this->purchaseRepo->all(request()->all());
		return response()->json(['batches'=>$batches]);
	}

	public function show($id)
	{
		$batch = $this->purchaseRepo->with('items')->findOrFail($id);
		return response()->json($batch);
	}

	public function showWithTrashed($id)
	{
		$batch = $this->purchaseRepo->with('itemsWithTrashed')->findOrFail($id);
		return response()->json($batch);
	}

	// search for batch by id - used in stock transfer
	public function findBatch($batchId, $merchantId, $channelId)
	{
		$batch = $this->purchaseRepo->findBatch($batchId, $merchantId, $channelId);
		if (!empty($batch)) {
			return json_encode(array("success"=> true, "items"=> $batch['items']));
		}
		else {
			return json_encode(array("success"=> false, "message"=> "The procurement batch was not found. Please try again."));
		}
	}

	public function store()
	{
		\DB::beginTransaction();
		$inputs = request()->all();
		$data = $this->purchaseRepo->create($inputs);
		$messages = array();
		$product = '';
		if(!empty($inputs['items']))
		{
			foreach($inputs['items'] as $item)
			{
				$item['client_id'] = $inputs['client_id'];
                $item['merchant_id'] = $inputs['merchant_id'];
			   	if($inputs['replenishment']!=1)
				{
					if (strcasecmp($item['product'], 'NEW') == 0 || $item['product'] == '0')
				 	{  
					 	$productRepo = new $this->productRepo;
					 	$brand = new $this->brandRepo(new Brand);
					 	$brand = $brand->where('prefix','=',$item['prefix'])
					 				->where('merchant_id','=', $inputs['merchant_id'])->first();
					 	// convert user-inputted category name to category id
					 	$item['category_id'] = $this->categoryRepo->whereRaw('full_name = "'.$item['category_id'].'"')->first()->id;
					 	$item['brand_id'] = !empty($brand->id)?$brand->id:null;
                        $item['brand'] = $item['prefix'];
                        unset($item['prefix']);
                        
					 	$product = $productRepo->create($item);

					 	//tags
						foreach($item['tags'] as $tag)
						{
							$sku_tag = new $this->tagRepo();
							$sku_tag->create(['value'=>$tag,'product_id'=>$product->id]);
						}
					}
				 	elseif($item['product'] != '')
					{
				 		$product = $this->productRepo->find( $item['product'] );

					}
					
					$item['product_id'] = $product->id;
					//\Log::info(print_r($item, true));
					// create sku under the product
                    $newSkuRepo = new $this->skuRepo;
					$sku = $newSkuRepo->create($item);
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

					$newSkuRepo->update( ['hubwire_sku'=>!empty($item['client_sku'])?$item['client_sku']:$this->HWSKU($sku->sku_id)], $sku->sku_id, 'sku_id');
				}

				$item['batch_id'] = $data->batch_id;
				$purchase_item = new $this->itemRepo(new PurchaseItem);
				$purchase_item->create($item);
			}
			$data = $this->purchaseRepo->with('items')->find($data->batch_id);
			if (!empty($messages))
				$data['errors'] = $messages;
		}
		\DB::commit();
		return response()->json($data);
	}

	public function update($id)
	{
		$inputs = request()->all();
		$model = $this->purchaseRepo->update($inputs,$id);
		return response()->json($model);
	}

	public function destroy($id)
	{
		$this->itemRepo->clear($id);
       	$acknowledge = $this->purchaseRepo->delete($id);
		return response()->json(['acknowledge'=>$acknowledge]);
	}

	public function receive($id)
	{
		$batch = $this->purchaseRepo->receive($id);
		return response()->json($batch);
	}

}
