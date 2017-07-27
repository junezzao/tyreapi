<?php
namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;

use App\Repositories\Eloquent\SyncRepository as SyncRepo;
use App\Repositories\ProductRepository as ProductRepo;
use App\Repositories\Criteria\Products\ByChannel;
use App\Repositories\SKURepository as SKURepo;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\Eloquent\ProductTagRepository as TagRepo;
use App\Repositories\CustomFieldsRepository;

use App\Models\Admin\ProductThirdPartyCategory;
use App\Models\Admin\ChannelSKU;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Activity;
//use Log;
use DB;

class InventoryController extends AdminController
{
	protected $authorizer;
	protected $productRepo;
	protected $SKURepo;
	protected $channelSKURepo;
	protected $cfRepo;

    public function __construct(
    	Authorizer $authorizer,
    	ProductRepo $productRepo
    	)
    {
        $this->middleware('oauth');
        $this->productRepo = $productRepo;
        $this->SKURepo = new SKURepo;
        // $this->channelSKURepo = $channelSKURepo;
        $this->authorizer = $authorizer;
        $this->cfRepo = new CustomFieldsRepository;
    }

	public function index()
	{
		// elasticsearch
		$data = $this->productRepo->search(request()->all());
		return response()->json([
			'start'=> intval(request()->get('start', 0)),
            'length' => intval(request()->get('length', 50)),
            'total' => $data->total,
            'products'=>$data->products
		]);
		// mysql
		// $data = $this->productRepo->with('brand','merchant');
		// return response()->json([
		// 'start'=> intval(request()->get('start', 0)),
        // 'length' => intval(request()->get('length', 50)),
        // 'total' => $data->count(),
        // 'products'=>$data->skip(request()->get('start', 0))->take(request()->get('length', 50))->all()
		// ]);
	}

	public function show($id)
	{
		$channel_id = request()->get('channel_id',null);
		
		if($channel_id) {
			$data = $this->productRepo->pushCriteria(new ByChannel($channel_id));
		}
		else{
			$data = $this->productRepo->with('sku_in_channel');
		}
		
		$data = $data->with('tags','media','brand','merchant','batch','default_media','category')
				->findOrFail($id);
		
		return response()->json($data);
	}

	public function store()
	{
		$inputs = request()->all();
		$data = $this->productRepo->create($inputs);
		return response()->json($data);
	}

	public function update($id)
	{
		$product = $this->productRepo->findOrFail($id);
		$update = $this->productRepo->massUpdate(request()->all(), $id);

		$productRepo = new ProductRepo;
		$data = $productRepo
				->with('sku_in_channel','tags','media','brand')
				->findOrFail($id);
		return response()->json($data);
	}

	public function destroy($id)
	{
		$acknowledge = $this->productRepo->delete($id);
		return response()->json(['acknowledge'=>$acknowledge?true:false]); 	
	}

	public function getFiltersData() {
		$filters = array();
        $filters['merchant_id'] = request()->get('merchant_id', null);
        $filters['channel_id'] = request()->get('channel_id', null);

		$data = $this->productRepo->getFiltersData($filters);

        return response()->json($data);
    }

    public function getProductDetails(Request $request)
    {
    	$channel_id = $request->input('channel_id');
    	$type = $request->input('type');
    	$keyword = $request->input('keyword');
        $item = $this->productRepo->getProductDetails($channel_id, $type, $keyword);
        return response()->json($item);
    }

    // Bulk update categories
    public function loadCategories() {
    	$ids = request()->get('ids');
    	$channelId = request()->get('channel_id');

    	$products = $this->productRepo->bulkLoadCategories($ids, $channelId);

        return response()->json($products);
    }

    public function saveCategories() {
    	$changes = request()->get('changes');
    	foreach($changes as $change) {
            // 0 - product_id
            // 1 - channel_id
            $ids = explode(",", $change[0]);
            $varName = $change[1];
            $categoryID = $change[4];

            $cat = ProductThirdPartyCategory::where('product_id', '=', $ids[0])
                    ->where('channel_id', '=', $ids[1])
                    ->first();

            if(is_null($cat)) {
                $new_cat = new ProductThirdPartyCategory;
                $new_cat->product_id = $ids[0];
                $new_cat->channel_id = $ids[1];
                $new_cat->cat_id = $categoryID;
                $new_cat->cat_name = $change[3];
                $new_cat->save();
            } else {
                $cat = ProductThirdPartyCategory::where('product_id', '=', $ids[0])
                        ->where('channel_id', '=', $ids[1])
                        ->update(array(
                            'cat_id' => $categoryID,
                            'cat_name' => $change[3]
                        ));
            }
        }
        return response()->json(['success'=>true]);
    }

    // channel inventory bulk update load function
    public function bulkLoad() {
    	$ids = request()->get('ids');
    	$channelId = request()->get('channel_id');

    	$products = $this->productRepo->bulkLoad($ids, $channelId);

        $customFields = $this->cfRepo->getCF($channelId);

        foreach ($products as $product) {
            if (!is_null($customFields)) {
                $fieldData = $this->cfRepo->getCFData($product->channel_sku_id);
                
                if (!is_null($fieldData)) {
                	// attach all associated custom fields + field values to the product object
                	foreach($customFields as $field) {
	                    foreach ($fieldData as $fData) {
	                        if ($fData['custom_field_id']==$field['id']) {
	                            $product->{$field['field_name'].'|'.$field['id']} = $fData['field_value'];
	                            $product->{$fData['custom_field_id']} = array('field_data_id' => $fData['id']);
	                            break;
	                        }
	                    }
	                }
                }   
            }
        }

        return response()->json(["products"=>$products, "customFields"=>$customFields]);
    }

    public function bulkSave() {
    	/*
         * fields that can be edited:
         * status       -> channel_sku_active -> channel sku
         * retail price -> channel_sku_price -> channel_sku
         * listing price   -> channel_sku_promo_price -> channel_sku
         * warehouse coordinates -> channel_sku_coordinates -> channel_sku
         * custom fields
         */
    	$changes = request()->get('changes');

        // customFieldsChanges[""] = ;
        $updateCustomFields = false;
        $customFieldsChanges = array();

        foreach($changes as $change) {

            // 0 - product_id
            // 1 - sku_id
            // 2 - channel_sku_id
            $ids = explode(",", $change[0]);

            $varName = $change[1];

            // if changing status
            if ($varName=="channel_sku_active") {
                $chSKU = ChannelSKU::find($ids[2]);
                $chSKU->$varName = ($change[3]=="INACTIVE")?0 : 1;
                $chSKU->save();
            }

            // if changing other fields
            else if ($varName=="channel_sku_price" || $varName=="channel_sku_promo_price" || $varName=="channel_sku_coordinates" || $varName=="promo_start_date" || $varName=="promo_end_date" ) {
                $chSKU = ChannelSKU::find($ids[2]);
                $chSKU->$varName = $change[3];
                $chSKU->save();
            }

            // if changing custom fields
            else {
            	$cfID = explode("|", $varName);
            	$data = array("channel_sku_id" => $ids[2], "custom_field_id" => $cfID[1], "field_value" => $change[3]);
            	if(isset($ids[3]))
            		$data['id'] = $ids[3];
                
                $this->cfRepo->updateCFData($data);
            }
        }
        
        return response()->json(['success'=>true]);
    }

    public function bulkReject(){
        $skus = request()->get('sku');
        $channelSKURepo = new ChannelSKURepo;
        $data = $channelSKURepo->bulkReject($skus);
        return response()->json($data);
    }

    public function syncImages($id){
        $data['product_id'] = $id;
        $syncRepo = new SyncRepo;
        $sync = $syncRepo->updateMedia($data);
        return response()->json(['success'=>true]);
    }

    public function byColInBulk($col)
    {
        $skuRepo = new SKURepo;
        $inputs = \Input::all();
        return response()->json($skuRepo->with(['productDetails','combinations','tags'])->whereIn($col, $inputs[$col]));
    }

    public function syncProducts(Request $request, $type) {
        $productIds = $request->input('products');
        $event = (strcasecmp($type, 'create') == 0) ? 'createProduct' : 'updateProduct';

        $return['total'] = count($productIds);
        $return['created'] = 0;
        $return['skipped_ids'] = array();
        $return['failed_data'] = array();

        $input['channel_id'] = $request->input('channel');
        foreach ($productIds as $productId) {
            $input['product_id'] = $productId;

            $syncRepo = new SyncRepo;
            $response = $syncRepo->{$event}($input);

            if ($response !== false && !empty($response->id)) {
                $return['created']++;
            }
            else if (!empty($response['code']) && $response['code'] == 422) {
                $return['failed_data'][] = array(
                    'product_id'    => $productId,
                    'error'         => $response['error']->all()
                );
            }
            else {
                $return['skipped_ids'][] = $productId;
            }
        }
        
        return response()->json($return);
    }

    public function getTpItemDetails(Request $request)
    {
        $item = ChannelSKU::where('ref_id', '=', $request->input('tp_ref_id'))->first();
        if($item){
            $response['hubwire_sku'] = $item->sku->hubwire_sku;
            $response['unit_price'] = $item->channel_sku_price;
            $response['sale_price'] = ($item->channel_sku_promo_price > 0 ? $item->channel_sku_promo_price : $item->channel_sku_price);
            $response['channel_sku_id'] = $item->channel_sku_id;
            return response()->json($response);
        }else{
       	    return response()->json(['success' => false, 'error' => "Third Party Item not found in database"]); 
	}
    }

    // Grabs a list of product ids and return products and associated skus & channel skus
    public function bulkLoadProducts(Request $request) {
        try {
            $ids = $request->input('ids');
            $products = DB::table('products')
                            ->join('sku', 'sku.product_id', '=', 'products.id')
                            ->join('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                            ->join('channels', 'channel_sku.channel_id', '=', 'channels.id')
                            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                            ->leftJoin(\DB::raw('(SELECT * from product_tags where deleted_at IS NULL) as product_tags'),'products.id','=','product_tags.product_id')
                            ->leftJoin('sku_combinations', 'sku_combinations.sku_id', '=', 'sku.sku_id')
                            ->leftJoin('sku_options', 'sku_options.option_id' ,'=', 'sku_combinations.option_id')
                            ->leftJoin('categories', 'categories.id','=','products.category_id')
                            ->whereIn('products.id', $ids)
                            ->whereNull('product_tags.deleted_at')
                            ->whereNull('channel_sku.deleted_at')
                            ->select('products.id', 'sku.sku_id', 'channel_sku.channel_sku_id', 'products.category_id',
                                DB::raw('products.name as name, channels.name as channel_name, brands.name as brand_name, categories.full_name as category_name'), 
                                'sku.hubwire_sku','sku.sku_supplier_code', 'channel_sku.channel_sku_quantity',
                                'channel_sku.channel_sku_price', 'channel_sku.channel_sku_promo_price',
                                'channel_sku.promo_start_date','channel_sku.promo_end_date',
                                'channel_sku.channel_sku_coordinates', 'sku.sku_weight',
                                DB::raw('GROUP_CONCAT(DISTINCT "<b>",sku_options.option_name, "</b>", \': \', sku_options.option_value ORDER BY sku_options.option_name ASC separator \', \') AS options'),
                            //    DB::raw('GROUP_CONCAT(DISTINCT sku_tag.tag_value separator \', \') AS tags'))
                                DB::raw('GROUP_CONCAT(DISTINCT product_tags.value ORDER BY product_tags.value ASC SEPARATOR \', \') AS tags'))
                            ->groupBy('channel_sku.channel_sku_id')
                            ->orderBy('products.name', 'asc')
                            ->orderBy('channels.name', 'asc')
                            ->get();
            return response()->json(['success' => true, 'data' => $products]);
        }
        catch(Exception $e)
        {
            return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }

    public function bulkSaveProducts(Request $request) {
        try {
            $channelSKURepo = new ChannelSKURepo;
            $skuRepo = new SKURepo;
            /*
             * fields that can be edited:
             * name -> products
             * supplier sku -> sku_supplier_code -> sku
             * sku weight   -> sku_weight -> sku
             * retail price -> channel_sku_price -> channel_sku
             * listing price   -> channel_sku_promo_price channel_sku
             * warehouse coordinates -> channel_sku_coordinates -> channel_sku
             */
            $changes = $request->input("changes");
            foreach($changes as $change) {
                // 0 - product_id
                // 1 - sku_id
                // 2 - channel_sku_id
                $ids = explode(",", $change[0]);
                $varName = $change[1];

                $obj = null;
                // if changing product
                if ($varName=="name" || $varName=="category_id") {
                    $obj = $this->productRepo->findOrFail($ids[0]);
                }

                // if changing sku
                else if ($varName=="sku_supplier_code" || $varName=="client_sku" || $varName=="sku_weight") {
                    $obj = $skuRepo->find($ids[1]);
                }

                else if ($varName=="tags"){
                    $tags_inputs = explode(',', $change[3]);
                    $tagRepo = new TagRepo;
                    $tagUpdate = $tagRepo->updateTagsByProduct($ids[0], $tags_inputs);

                    return response()->json(['success' => true, 'message' => 'Your changes were successfully saved.']);
                }

                // if changing channel sku
                else {
                    $obj = $channelSKURepo->findOrFail($ids[2]);
                }
                $obj->$varName = strtolower($change[3])=='null'?null:$change[3];
                $obj->save();
            }
            return response()->json(['success' => true, 'message' => 'Your changes were successfully saved.']);
        }
        catch(Exception $e)
        {
            return response()->json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
        }
    }

    public function bulkDelete(){
        $products = request()->get('products');
        $data = $this->productRepo->bulkDelete($products);
        return response()->json($data);
    }

    public function getSkusByChannel() {
        $data = request()->all();
        return $this->productRepo->getSkusByChannel($data['columns']['channel_id'], $data['columns']['product']);
    }


    public function getBulkSkus(Request $request)
    {
        $ids = $request->get('product_ids');
        return response()->json($this->productRepo->with('sku_in_channel')->whereIn('id', $ids)->all());
    }

    public function getChannelSkusByChannel($channelId)
    {
        $data = $this->productRepo->getChannelSkusByChannel($channelId);
        
        return response()->json($data);
    }

    public function getProducts(Request $request)
    {
        $ids = $request->get('product_ids');
        return response()->json($this->productRepo->with('default_media', 'brand', 'sku_in_channel')->whereIn('id', $ids)->all());
    }

    public function getStockMovements($by, $id) 
    {
        return response()->json($this->SKURepo->getStockMovements($by, $id));
    }

    public function getProductsByBrand($brandId)
    {
        $es = true;
        return response()->json($this->productRepo->getProductsByBrand($brandId, $es));
    }
}
