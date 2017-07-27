<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;

use App\Models\Admin\Product;
use App\Models\Admin\Channel;
use App\Models\Admin\Merchant;
use App\Models\Admin\Brand;
use App\Models\Admin\ProductTag;
use App\Models\Admin\Supplier;
use App\Models\Admin\SKU;

use App\Repositories\Repository;
use App\Repositories\Eloquent\SyncRepository;
use App\Repositories\SKURepository as SKURepo;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\Eloquent\SKUOptionRepository as SKUOptionRepo;
use App\Repositories\Eloquent\SKUCombinationRepository as SKUCombinationRepo;
use App\Repositories\Eloquent\ProductTagRepository as TagRepo;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\MerchantRepository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use App\Http\Controllers\Admin\AdminController;
use App\Repositories\Criteria\Products\ByBrand;
// use Shift31\LaravelElasticsearch\Facades\Es;


class ProductRepository extends Repository
{

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    protected $user_id;


    public function __construct()
    {
        $this->model = new Product;
        $this->user_id = Authorizer::getResourceOwnerId();
        parent::__construct();
    }

    public function model()
    {
        return 'App\Models\Admin\Product';
    }

    public function create(array $data)
    {
        $rules = [
           'name'  => 'required',
           'description' => 'sometimes',
           'merchant_id' => 'required|exists:merchants,id',
           'default_media' => 'sometimes|required|exists:product_media,media_id',
           'brand' => 'required|exists:brands,prefix',
           'description2' => 'sometimes|required',
           'brand_id' => 'required|integer|exists:brands,id',
           'active' => 'sometimes|required|boolean',
        ];
        $messages = array();
        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        $data['name'] = htmlentities(($data['name']));
        $model = parent::create($data);
        Activity::log('Product '.$model->name.' was created', $this->user_id);
        $product = $this->find($model->id)->updateElasticsearch();
        return $product;
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $product = $this->find($id);
        $rules = [
           'name'  => 'sometimes|required',
           'description' => 'sometimes',
           'client_id' => 'sometimes|required|exists:clients',
           'merchant_id' => 'sometimes|required|exists:merchants,id',
           'default_media' => 'sometimes|required|exists:product_media,id',
           'brand' =>'sometimes|required|exists:brands,prefix',
           'description2' => 'sometimes|required',
           'brand_id' => 'sometimes|required|integer|exists:brands,id',
           'active' => 'sometimes|required|boolean'
        ];
        $messages = array();

        if(isset($data['active']))
        {
            if(intval($data['active'])===0)
            {
                // Check channel SKU
                $channel_skus = $product->channel_sku()->isActive()->get();
                $rules['active_channel_skus'] = 'integer|max:0';
                $rules['quantity_channel_skus'] = 'integer|max:0';
                $data['active_channel_skus'] = $channel_skus->count();
                $data['quantity_channel_skus'] = $product->channel_sku()->sum('channel_sku_quantity');
                $messages['active_channel_skus.max'] = 'Cannot deactivate product while having active sku(s) in channel(s).';
                $messages['quantity_channel_skus.max'] = 'Cannot deactivate product while having quantity in channel(s).';

            }
            else if(intval($data['active'])===1)
            {
                // Check active brand
                $brand = $product->brand()->first();
                $rules['active_brand'] = 'integer|min:1';
                $data['active_brand'] = $brand->active==1?1:0;
                $messages['active_brand.min'] = 'Cannot activate product when brand inactive.';
            }
        }

        $v = \Validator::make($data, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        if(isset($data['active_channel_skus'])) unset($data['active_channel_skus']);
        if(isset($data['quantity_channel_skus'])) unset($data['quantity_channel_skus']);
        if(isset($data['active_brand'])) unset($data['active_brand']);


        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        if(isset($data['name']))
            $data['name'] = htmlentities(($data['name']));
        $ack = parent::update($data, $id, $attribute);

        $syncRepo = new SyncRepository;
        $input['product_id'] = $id;
        $sync = $syncRepo->updateProduct($input);


        return $product;
    }

    public function delete($id, $reason='')
    {
        $data = array();

        $product = $this->findOrFail($id);
        $data['quantity'] = $product->quantity;

        $channelSkus = $this->find($id)->channel_sku;
        $totalOrderItemCount = 0;
        foreach($channelSkus as $channelSku) {
            $channelSKURepo = new ChannelSKURepo;
            $orderItems = $channelSKURepo->find($channelSku->channel_sku_id)->orderItems;
            $totalOrderItemCount += count($orderItems);
        }
        $data['sales_count'] = $totalOrderItemCount;

        $rules = [
           'quantity' => 'integer|max:0',
           'sales_count' => 'integer|max:0',
        ];
        $messages = [
           'quantity.max' => 'Cannot delete product while having quantity.',
           'sales_count.max' => 'Cannot delete product while having sales.',
        ];
        $v = \Validator::make($data, $rules, $messages);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $product = $this->find($id);
        $ack = parent::delete($id);

        Activity::log('Product ('.$id.') was deleted due to '.$reason.'.', $this->user_id);
        $response['success'] = true;
        return $response;
    }

    public function bulkDelete(array $products){
        \DB::beginTransaction();
        foreach($products as $productId => $reason){
            $response = $this->delete($productId, $reason);
            if(!empty($response->error)) {
                return $response;
            }
        }
        \DB::commit();
        $response['success'] = true;
        return $response;
    }

    public function search($data){
        $search = array();
        $param = array();
        $mustparam = array();
        $sku_param = array();
        $sku_must_param = array();
        $nested_must = array();
        $nested_should = array();
        $search = isset($data['columns'])?$data['columns']:null;
        $searchParams = array();
        $searchParams['index'] = env('ELASTICSEARCH_INDEX','products');
        $searchParams['type'] = 'inventory';

        $searchParams['from'] = !empty($data['start'])?$data['start']:0;
        $searchParams['size'] = !empty($data['length'])?$data['length']:50;

        if (isset($search['client_id']) && $search['client_id']){
            $mustparam[] = array('match' => array('client_id' => $search['client_id']));
        }

        if (isset($search['status'])){
            $mustparam[] = array('match' => array('active' => $search['status']=="1"?true:false));
        }

        if (isset($search['merchant_id']) && $search['merchant_id']){
            $mustparam[] = array('match' => array('merchant_id' => $search['merchant_id']));
        }

        if (isset($search['supplier_id']) && $search['supplier_id']){
            $mustparam[] = array('match' => array('supplier_id' => $search['supplier_id']));
        }

        if (isset($search['purchase_id']) && $search['purchase_id']){
            if(intval($search['purchase_id']) > 0 ){
                $sku_must_param[] = array('match' => array('batch_id' => intval($search['purchase_id'])));
            }
        }

        if (isset($search['brand']) && $search['brand']){
            $mustparam[] = array('match' => array('brand.id' => $search['brand'] ));
        }

        if (isset($search['no_image']) && intval($search['no_image']) == 1){
            $searchParams['body']['filter'] = array('missing' => array('field'=>'media'));
        }

        if (isset($search['product']) && $search['product']){
            $param[] = array('wildcard' => array('name' => strtolower($search['product']).'*'));
            $param[] = array('match_phrase' => array('name' => strtolower($search['product'])));
            $param[] = array('match_phrase' => array('brand.name' => strtolower($search['product'])));
            if(intval($search['product']) > 0 ){
                $param[] = array('match' => array('id' => intval($search['product']) ));
            }
            $param[] = array('match_phrase' => array('merchant_name' => strtolower($search['product'])));
            $sku_param[] = array('match_phrase' => array('client_sku' => strtolower($search['product'])));
            $sku_param[] = array('match_phrase' => array('sku_supplier_code' => strtolower($search['product'])));
            $sku_param[] = array('match_phrase' => array('hubwire_sku' => strtolower($search['product'])));
            $sku_param[] = array('match_phrase' => array('channel_sku_coordinates' => strtolower($search['product'])));
        }
        
        if(isset($search['coordinate'])){
            $sku_param[] = array('match_phrase' => array('channel_sku_coordinates' => strtolower($search['coordinate'])));
        }

        if (isset($search['max_price']) || isset($search['min_price']))
        {
            $price = [];
            if(isset($search['max_price']))
                $price['channel_sku_price']['lte'] = $search['max_price'];
            if(isset($search['min_price']))
                $price['channel_sku_price']['gte'] = $search['min_price'];

            $tmp['bool']['should'][] = array(
                                            'bool'=> array(
                                                'must' => array(
                                                    'range'=> $price
                                                )
                                            )
                                        );
            $sku_must_param[] = $tmp;
        }


        if (isset($search['options']) && $search['options']){
            $options = explode('|', $search['options']);
                foreach ($options as $key => $option){
                    $sku_param[] = array('match' => array('option_value' => strtolower($option)));
                }
        }

        if (isset($search['tag_value']) && $search['tag_value']){
            $tags = explode('|', $search['tag_value']);

            foreach ($tags as $tag){
                $mustparam[] = array('match' => array('value' => $tag));
            }
        }

        if (isset($search['has_stock']))
        {
            $key_stock = intval($search['has_stock'])===0?'lt':'gte';
            $param[] = array(
                            'bool'=> array(
                                'must' => array(
                                    'range'=> array(
                                        'quantity' => array($key_stock=>1)
                                        )
                                )
                            )
                        );
        }
        $channel_id = null;
        if (isset($search['channel_id']) && $search['channel_id']){
            if(intval($search['channel_id']) > 0 ){
                $channel_id = intval($search['channel_id']);
                $sku_must_param[] = array('match' => array('channel_id' => intval($search['channel_id'])));
            }

            if(isset($search['activeOnly']) && $search['activeOnly']){
                $sku_must_param[] = array('match' => array('channel_sku_active' => '1'));
            }

            if(isset($search['channel_sku_active']) && $search['channel_sku_active']) {
                $sku_must_param[] = array('match' => array('channel_sku_active' => $search['channel_sku_active']));
            }

            if (isset($search['has_stock']) && $search['has_stock']){
                $key_stock = intval($search['has_stock'])===0?'lt':'gte';
                $sku_must_param[] = array(
                                        'bool'=> array(
                                            'must' => array(
                                                'range'=> array(
                                                    'channel_sku_quantity' => array($key_stock=>1)
                                                    )
                                            )
                                        )
                                    );
            }
            if (isset($search['hasQuantity']) && $search['hasQuantity']){
                $quantity = array();
                $quantity['bool']['should'][] = array('bool'=> array(
                                                        'must' => array(
                                                            'range'=> array(
                                                                'channel_sku_quantity' => array('gte'=>1)
                                                                )
                                                        )
                                                    ));
                $quantity['bool']['should'][] = array('bool'=> array(
                                                        'must' => array(
                                                            'range'=> array(
                                                                'shared_quantity' => array('gte'=>1)
                                                                )
                                                        )
                                                    ));
                $sku_must_param[] = $quantity;
            }
        }

        if (isset($search['category_id'])){
            $mustparam[] = array('match' => array('category_id' => intval($search['category_id'])));
        }

        if (isset($data['sort'])){
            foreach($data['sort'] as $sort){
                $searchParams['body']['sort'][] = array($sort['key'] => array('order' => $sort['order']));
            }
        }else{
            $searchParams['body']['sort'][] = array('updated_at' => array('order' => 'desc'));
        }

        if (isset($search['sync_status'])){
            $sku_must_param[] = array('match' => array('sync_status' => $search['sync_status']));
        }

        if(count($sku_param)>0 || count($sku_must_param)>0){
            if(count($sku_must_param)>0){
                $nested_must['nested']['path'] = 'sku_in_channel';
                $nested_must['nested']['query']['bool']['must'] = $sku_must_param;
                // $nested_must['nested']['query']['bool']['minimum_should_match'] = 1;
            }
            if(count($sku_param)>0){
                $nested_should['nested']['path'] = 'sku_in_channel';
                $nested_should['nested']['query']['bool']['should'] = $sku_param;
                $nested_should['nested']['query']['bool']['minimum_should_match'] = 1;
            }
        }
        if(count($nested_must)>0){
            $mustparam[] = $nested_must;
        }

        if(count($nested_should)>0){
            $param[] = $nested_should;
        }
        if(count($mustparam) > 0){
            $searchParams['body']['query']['bool']['must'] = $mustparam;
        }

        if(count($param) > 0){
            $searchParams['body']['query']['bool']['should'] = $param;
            $searchParams['body']['query']['bool']['minimum_should_match'] =1;
        }

        //\Log::info(json_encode($searchParams));
        $search_es = json_decode(json_encode(\Es::search($searchParams)));

        $ids = array();
        $search_es->products = array();
        foreach ($search_es->hits->hits as $id => $data) {
            //filter out skus that does not belong to the chosen channel
            if (isset($search['channel_id']) && $search['channel_id'] && intval($search['channel_id']) > 0){
                $channel_id = intval($search['channel_id']);

                foreach ($data->_source->sku_in_channel as $channelSkuKey => $channelSku) {
                    if ($channelSku->channel_id != $channel_id) {
                        unset($search_es->hits->hits[$id]->_source->sku_in_channel[$channelSkuKey]);
                    }
                }

                $search_es->hits->hits[$id]->_source->sku_in_channel = array_values($search_es->hits->hits[$id]->_source->sku_in_channel);
            }

            $search_es->products[] = $search_es->hits->hits[$id]->_source;
        }

        $response  = new \stdClass();
        $response->products = $response->result = $search_es->products;
        $response->total = $search_es->hits->total;
        return $response;
    }


    public function apiResponse()
    {
        return $this->result;
    }

    public function getFiltersData($filters=array()) {
        $data = array(
            'gsts'              => array(),
            'channels'          => array(),
            'merchants'         => array(),
            'suppliers'         => array(),
            'tags'              => array(),
            'keywords'          => array(),
            'statuses'          => array('1' => 'Active', '0' => 'Inactive'),
            'stock_statuses'    => array('1' => 'In Stock', '0' => 'Out of Stock')
        );

        $merchant_id = null;
        $channel_id = null;
        if(isset($filters['merchant_id']) && !empty($filters['merchant_id'])) $merchant_id = $filters['merchant_id'];
        if(isset($filters['channel_id']) && !empty($filters['channel_id'])) $channel_id = $filters['channel_id'];

        //merchants list
        $merchants = Merchant::select('id', 'name')->get();
        if(!is_null($channel_id)) {
            $merchantRepo = new MerchantRepository(new Merchant);
            $merchants = $merchantRepo->all(array('channel_id'=>$channel_id));
        }
        foreach ($merchants as $merchant) {
            $data['merchants'][$merchant->id] = $merchant->name;
        }

        //channels and suppliers list
        $channels = Channel::with('channel_type')->get();
        $suppliers = Supplier::select('id', 'name')->get();

        if (!is_null($merchant_id)) {
            $channels = Channel::with('channel_type')->where('merchant_id', '=', $merchant_id)->get();
            $suppliers = Supplier::select('id', 'name')->where('merchant_id', '=', $merchant_id)->get();
        }
        elseif (!is_null($channel_id)) {
            $channels = Channel::with('channel_type')->where('id', '=', $channel_id)->get();
            $supplierRepo = new SupplierRepository;
            $suppliers = $supplierRepo->all(array('channel_id'=>$channel_id));
        }

        foreach ($channels as $channel) {
            $data['channels'][] = array(
                'id'            => $channel->id,
                'name'          => $channel->name,
                'type'          => $channel->channel_type->name,
                'third_party'   => $channel->channel_type->third_party,
                'status'        => $channel->status,
            );
        }

        foreach ($suppliers as $supplier) {
            $data['suppliers'][$supplier->id] = $supplier->name;
        }

        /*
         * keywords list : product name, brand name, merchant name, hubwire sku
         */
        $query = [
            'index' => env('ELASTICSEARCH_INDEX','products'),
            'type'  =>'inventory',
            'body'  => [
                'size'  => 0,
                'aggs'  => [
                    'results' => [
                        'terms' => [
                            'size'  => 0
                        ]
                    ]
                ]
            ]
        ];

        //product names
        $query['body']['aggs']['results']['terms']['field'] = 'name.raw';
        $productNames = array_pluck(json_decode(json_encode(\Es::search($query)), true)['aggregations']['results']['buckets'], 'key');

        //brand name
        $query['body']['aggs']['results']['terms']['field'] = 'brand.name.raw';
        $brandNames = array_pluck(json_decode(json_encode(\Es::search($query)), true)['aggregations']['results']['buckets'], 'key');

        //merchant names
        $query['body']['aggs']['results']['terms']['field'] = 'merchant_name.raw';
        $merchantNames = array_pluck(json_decode(json_encode(\Es::search($query)), true)['aggregations']['results']['buckets'], 'key');

        //hubwire skus
        $hubwireSkuQuery = [
            'index' => env('ELASTICSEARCH_INDEX','products'),
            'type'  =>'inventory',
            'body'  => [
                'size'  => 0,
                'aggs'  => [
                    'sku_in_channel' => [
                        'nested'    => [
                            'path'  => 'sku_in_channel'
                        ],
                        'aggs'  => [
                            'results' => [
                                'terms' => [
                                    'size'  => 0,
                                    'field' => 'sku_in_channel.sku.hubwire_sku.raw'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $hubwireSkus = array_pluck(json_decode(json_encode(\Es::search($hubwireSkuQuery)), true)['aggregations']['sku_in_channel']['results']['buckets'], 'key');

        // $productNames = array_pluck(Product::select('name')->distinct()->get()->toArray(), 'name');
        // $brandNames = array_pluck(Brand::select('name')->distinct()->get()->toArray(), 'name');
        // $merchantNames = array_pluck($merchants->toArray(), 'name');
        // $hubwireSkus = array_pluck(SKU::select('hubwire_sku')->get()->toArray(), 'hubwire_sku');

        $data['keywords'] = array_merge($productNames, $brandNames, $merchantNames, $hubwireSkus);

        // KEYWORD END //

        //tags
        // $data['tags'] = array_pluck(ProductTag::select('value')->distinct()->get()->toArray(), 'value');
        $query['body']['aggs']['results']['terms']['field'] = 'tags.value';
        $data['tags'] = array_pluck(json_decode(json_encode(\Es::search($query)), true)['aggregations']['results']['buckets'], 'key');

        return $data;
    }

    public function getSkusByChannel($channel_id, $product_id) {
        $data = Product::with('tags','media','brand','merchant','batch')
                ->with(['sku_in_channel' => function($query) use ($channel_id) {
                    $query->where('channel_sku.channel_id', '=', $channel_id);
                }])
                ->findOrFail($product_id);

        return $data;
    }

    public function getProductDetails($channel_id, $type, $keyword)
    {
        /*$item = SKU::select('sku.hubwire_sku', 'channel_sku.channel_id', 'channel_sku.sku_id', 'sku.sku_id', 'sku.product_id')
                            ->join('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                            ->where('sku.'.$type, '=', $keyword)
                            ->where('channel_sku.channel_id', '=', $channel_id)
                            ->get();
        */
        // get the sku with the latest/highest product_id in the event that there are two different skus with the same hubwire_sku
        $sku = SKU::where($type, '=', $keyword)->orderBy('product_id', 'desc')->first();
        if(!is_null($sku)){
            $product = $sku->product()->with('sku_in_channel')->first();
            $channelSkuDetails = $sku->ChannelSKUs;

            return ['sku' =>$sku, 'product' => $product, 'channel_sku' => $channelSkuDetails];
        }
        return false;
    }

    public function bulkLoad($ids, $channelId) {
        $products = Product::
                        join('sku', 'sku.product_id', '=', 'products.id')
                            ->join('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                            ->leftJoin('product_third_party_categories', function($join) {
                                $join->on('product_third_party_categories.product_id', '=', 'products.id')
                                ->on('product_third_party_categories.channel_id', '=', 'channel_sku.channel_id');
                            })
                            ->leftJoin('product_third_party', function($join) {
                                $join->on('product_third_party.product_id', '=', 'products.id')
                                ->on('product_third_party.channel_id', '=', 'channel_sku.channel_id');
                            })
                            ->leftJoin('channel_sku_extension', 'channel_sku.channel_sku_id', '=', 'channel_sku_extension.channel_sku_id')
                            ->leftJoin('sku_tag','sku.sku_id','=','sku_tag.sku_id')
                            ->leftJoin('sku_combinations', 'sku_combinations.sku_id', '=', 'sku.sku_id')
                            ->leftJoin('sku_options', 'sku_options.option_id' ,'=', 'sku_combinations.option_id')
                            ->leftJoin('categories', 'categories.id','=','products.category_id')
                            ->whereIn('products.id', $ids)
                            ->where('channel_sku.channel_id', '=', $channelId)
                            //->where('products.client_id', '=', $client_id)
                            ->whereNull('sku_tag.deleted_at')
                            ->whereNull('channel_sku.deleted_at')
                            //->whereNull('product_third_party.ref_id')
                            ->select('products.id', 'sku.sku_id', 'channel_sku.channel_sku_id','categories.full_name as category_name',
                                'products.name', 'products.brand_id', 'sku.hubwire_sku','sku.client_sku', 'products.category_id',
                                'sku.sku_supplier_code', 'product_third_party_categories.cat_id',
                                'channel_sku.channel_sku_quantity',
                                'channel_sku_extension.shared_quantity',
                                'channel_sku.channel_sku_price', 'channel_sku.channel_sku_promo_price',
                                'channel_sku.promo_start_date', 'channel_sku.promo_end_date',
                                'channel_sku.channel_sku_coordinates', 'channel_sku.channel_id',
                                \DB::raw('GROUP_CONCAT(DISTINCT "<b>",sku_options.option_name, "</b>", ": ", sku_options.option_value ORDER BY sku_options.option_name ASC separator ", ") AS options'),
                                \DB::raw('GROUP_CONCAT(DISTINCT sku_tag.tag_value separator ", ") AS tags'),
                                \DB::raw('(CASE WHEN channel_sku.channel_sku_active = 0 THEN "INACTIVE" ELSE "ACTIVE" END) AS channel_sku_active'))
                            ->groupBy('channel_sku.channel_sku_id')
                            ->orderBy('products.name', 'asc')
                            ->get();
        return $products;
    }

    public function bulkLoadCategories($ids, $channelId) {
        $products = Product::
                        join('sku', 'sku.product_id', '=', 'products.id')
                        ->join('channel_sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
                        ->leftJoin('product_third_party_categories', function($join) {
                            $join->on('product_third_party_categories.product_id', '=', 'products.id')
                            ->on('product_third_party_categories.channel_id', '=', 'channel_sku.channel_id');
                        })
                        ->leftJoin('product_third_party', function($join) {
                            $join->on('product_third_party.product_id', '=', 'products.id')
                            ->on('product_third_party.channel_id', '=', 'channel_sku.channel_id');
                        })
                        ->whereIn('products.id', $ids)
                        ->where('channel_sku.channel_id', '=', $channelId)
                        //->whereNull('product_third_party.ref_id')
                        ->select('products.id', 'sku.sku_id', 'channel_sku.channel_sku_id',
                            'products.name', 'products.brand_id', 'sku.hubwire_sku','sku.client_sku',
                            'sku.sku_supplier_code', 'product_third_party_categories.cat_id',
                            'product_third_party_categories.cat_name')
                        ->groupBy('channel_sku.channel_sku_id')
                        ->orderBy('products.name', 'asc')
                        //->toSql();
                        ->get();
        return $products;
    }

    public function getExistingTags() {
        $query = [
            'index' => env('ELASTICSEARCH_INDEX','products'),
            'type'  =>'inventory',
            'body'  => [
                'size'  => 0,
                'aggs'  => [
                    'results' => [
                        'terms' => [
                            'field' => 'tags.value',
                            'size'  => 0
                        ]
                    ]
                ]
            ]
        ];

        return array_pluck(json_decode(json_encode(\Es::search($query)), true)['aggregations']['results']['buckets'], 'key');
    }

    public function massUpdate($inputs, $id)
    {
        $adminController = new AdminController;
        $product_inputs     =   !empty($inputs['product'])?$inputs['product']:null;
        $sku_inputs         =   !empty($inputs['sku'])?$inputs['sku']:null;
        $c_sku_inputs       =   !empty($inputs['sku_in_channel'])?$inputs['sku_in_channel']:null;
        $tags_inputs        =   !empty($inputs['tags'])?$inputs['tags']:null;
        if(is_string($tags_inputs)) $tags_inputs = array_filter(explode(',',$tags_inputs));

        if(!empty($product_inputs))
        {
            $product_data = $this->update($product_inputs,$id);
        }
        if(!empty($sku_inputs))
        {
            foreach($sku_inputs as $sku_input)
            {
                $SKURepo = new SKURepo;
                $sku = $SKURepo->where('product_id','=',$id)->findOrFail($sku_input['sku_id']);
                if(isset($sku_input['options']))
                {
                    $combinationRepo = new SKUCombinationRepo;
                    $combination = $combinationRepo->where('sku_id','=', $sku_input['sku_id'])->all();
                    foreach($combination as $comb)
                    {
                        $comb->delete();
                    }
                    foreach($sku_input['options'] as $k => $v)
                    {
                        //options
                        $optionRepo = new SKUOptionRepo;
                        $option = $optionRepo->where('option_name','=',$k)->where('option_value','=',$v)->first();
                        if(!$option)
                        $option = $optionRepo->create(['option_name'=>$k,'option_value'=>$v]);
                        //combinations
                        $combinationRepo = new SKUCombinationRepo;
                        $combination = $combinationRepo->create(['option_id'=>$option->option_id,'sku_id'=>$sku->sku_id]);
                    }
                    unset($sku_input['options']);
                    if(empty($sku->client_sku))
                    $sku->update( ['hubwire_sku'=>$adminController->HWSKU($sku->sku_id)] );
                    
                }
                $sku_data = $SKURepo->update($sku_input,$sku_input['sku_id']);

            }
        }
        if(!empty($c_sku_inputs))
        {   
            foreach($c_sku_inputs as $c_sku_input)
            {
                $channelSKURepo = new ChannelSKURepo;
                $channel_sku = $channelSKURepo->where('product_id','=',$id)->findOrFail($c_sku_input['channel_sku_id']);
                $cs_data = $channelSKURepo->update($c_sku_input,$c_sku_input['channel_sku_id']);
            }
        }
        if(!empty($tags_inputs))
        {
            $tagRepo = new TagRepo;
            $tagUpdate = $tagRepo->updateTagsByProduct($id, $tags_inputs);
            // Moved update code to tag repository.
        }
    }

    public function getChannelSkusByChannel($channelId)
    {
        $channelSKURepo = new ChannelSKURepo;
        $channel_sku = $channelSKURepo->findAllBy('channel_id', $channelId);
        
        return $channel_sku;
    }

    public function getProductsByBrand($brandId, $es = true)
    {
        if($es){
            $search = array(
                'columns' => array(
                    'brand' => $brandId,
                ),
                'start' => 0,
                'length' => 10000,
                'sort' => array(
                    array(
                        'key' => 'name.raw',
                        'order' => 'asc',
                    ),
                ),
            );
            // get products by elasticsearch
            $response = $this->search($search);
            // \Log::info(print_r($response, true));
        }else{
            // get thru db
            $this->pushCriteria(new ByBrand($brandId));
            $response  = new \stdClass();
            $response->products = $this->all();
        }

        return $response;
    }
}
