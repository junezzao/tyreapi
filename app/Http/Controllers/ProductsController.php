<?php namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\ProductRepository as ProductRepo;
use App\Repositories\CustomFieldsRepository as CFRepo;
use App\Models\Admin\Product;
use App\Models\Admin\ChannelSKU;
use App\Repositories\Criteria\Products\ByChannel;
use App\Repositories\Criteria\Products\ByMerchant;
use App\Repositories\Criteria\Products\CreatedAt;
use App\Repositories\Criteria\Products\SinceId;
use App\Repositories\Criteria\Products\UpdatedAt;
use App\Repositories\Criteria\Products\WithChanges;
use Cache;

class ProductsController extends Controller
{

    /**
     * @var Product
     */
    private $product;
    private $cfRepo;

    public function __construct(ProductRepo $product)
    {
        $this->cfRepo = new CFRepo;
        $this->product = $product;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::except('access_token');
        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            $this->product->pushCriteria(new ByChannel($this->inputs['channel_id']));
            $this->customFields = collect($this->cfRepo->getCF($this->inputs['channel_id']));
        }
        if ($this->oauth->authenticatable_type === 'Client') {
            $this->inputs['client_id'] = $this->oauth->authenticatable_id;
            $this->product->pushCriteria(new ByClient($this->inputs['client_id']));
        }
        if ($this->oauth->authenticatable_type === 'Merchant') {
            $this->inputs['merchant_id'] = $this->oauth->authenticatable_id;
            $this->product->pushCriteria(new ByMerchant($this->inputs['merchant_id']));
        }
    }

    public function index()
    {
        $rules = [
            'created_at' => 'sometimes|required|date|date_format:Y-m-d',
            'updated_at' => 'sometimes|required|date|date_format:Y-m-d',
            'sinceid' => 'sometimes|required|integer|min:1',
            'changed' => 'sometimes|required|boolean',
            'start' => 'sometimes|required|integer|min:0',
            'limit' => 'sometimes|required|integer|min:1|max:'.config('api.response.limit')
        ];
        
        $v = \Validator::make($this->inputs, $rules);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        
        if (!empty($this->inputs['created_at'])) {
            $this->product->pushCriteria(new CreatedAt($this->inputs['created_at']));
        }
        if (!empty($this->inputs['sinceid'])) {
            $this->product->pushCriteria(new SinceId($this->inputs['sinceid']));
        }
        if (!empty($this->inputs['updated_at'])) {
            $this->product->pushCriteria(new UpdatedAt($this->inputs['updated_at']));
        }
        if (isset($this->inputs['changed'])) {
            $this->product->pushCriteria(new WithChanges($this->inputs['changed']));
        }
        
        $products = $this->product->with('media','default_media','tags','brand');
        $count = $products->count();
        $products = $products->skip(request()->get('start', 0))->take(request()->get('limit', 50))->all();
        
        $products = Product::apiResponse($products);

        if(!empty($products))
        {
            // for each product, insert custom fields
            foreach($products as $product) {
                $customFieldsProductLevel = new \stdClass();
                foreach ($product->sku as $sku) {
                    $channel_sku_id = ChannelSKU::where('channel_id', $this->inputs['channel_id'])->where('sku_id', $sku->id)->firstOrFail()->channel_sku_id;
                    $cfData = $this->cfRepo->getCFData($channel_sku_id);
                    $customFieldsSkuLevel = new \stdClass();
                    $skuStr = 'SKU-';
                    foreach($this->customFields as $cf)
                    {
                        if (substr($cf['field_name'], 0, strlen($skuStr)) == $skuStr)
                        {
                            $fieldName = substr($cf['field_name'], strlen($skuStr));
                            $customFieldsSkuLevel->{$fieldName} = $cf['default_value'];
                        }
                        else
                        {
                            $customFieldsProductLevel->{$cf['field_name']} = $cf['default_value'];
                        }
                    }
                  
                    foreach ($cfData as $value) {
                        $customField = $this->cfRepo->getCFById($value['custom_field_id']);
                        
                        // if sku level
                        if (substr($customField['body']['field_name'], 0, strlen($skuStr)) == $skuStr) { 
                            if ($channel_sku_id==$value['channel_sku_id']) {
                                $fieldName = substr($customField['body']['field_name'], strlen($skuStr));
                                $customFieldsSkuLevel->{$fieldName} = !empty($value['field_value'])?$value['field_value']:$customFieldsSkuLevel->{$fieldName};
                            }
                        }
                        else 
                            $customFieldsProductLevel->{$customField['body']['field_name']} = !empty($value['field_value'])?$value['field_value']:$customFieldsProductLevel->{$customField['body']['field_name']};

                    }
                    // if sku has custom fields
                    // if (!empty((array) $customFieldsSkuLevel))
                        $sku->custom_fields = !empty((array) $customFieldsSkuLevel)?$customFieldsSkuLevel : null;
                }
                // if product has custom fields
                // if (!empty((array) $customFieldsProductLevel))
                $product->custom_fields = !empty((array) $customFieldsProductLevel) ? $customFieldsProductLevel : null;
            }
        }
        
        return response()->json([
            'code' => config('globals.status_code.OK_STATUS'),
            'start'=> intval(request()->get('start', 0)),
            'limit' => intval(request()->get('limit', 50)),
            'total' => $count,
            'products'=> $products
            ]);
    }

    public function show($id)
    {
        $product = Product::apiResponse($this->product->with('brand', 'media','tags','default_media')->findOrFail($id), $this->product->getCriteria());
        
        $customFieldsProductLevel = new \stdClass();
        foreach ($product->sku as $sku) {
            $channel_sku_id = ChannelSKU::where('channel_id', $this->inputs['channel_id'])->where('sku_id', $sku->id)->firstOrFail()->channel_sku_id;
            $cfData = $this->cfRepo->getCFData($channel_sku_id);
            //dd($sku);
            $customFieldsSkuLevel = new \stdClass();
            $skuStr = 'SKU-';
            foreach($this->customFields as $cf)
            {
                if (substr($cf['field_name'], 0, strlen($skuStr)) == $skuStr)
                {
                    $fieldName = substr($cf['field_name'], strlen($skuStr));
                    $customFieldsSkuLevel->{$fieldName} = !empty($value['field_value'])?$value['field_value']:$customFieldsSkuLevel->{$fieldName};
                }
                else
                {
                    $customFieldsProductLevel->{$cf['field_name']} = $cf['default_value'];
                }
            }
           
            foreach ($cfData as $value) {
                $customField = $this->cfRepo->getCFById($value['custom_field_id']);
                $skuStr = 'SKU-';

                // if sku level
                if (substr($customField['body']['field_name'], 0, strlen($skuStr)) == $skuStr) { 
                    if ($channel_sku_id==$value['channel_sku_id']) {
                        $fieldName = substr($customField['body']['field_name'], strlen($skuStr));
                        // if (!isset($customFieldsSkuLevel->{$fieldName}))
                            $customFieldsSkuLevel->{$fieldName} = $value['field_value'];
                    }
                }
                else {

                    // if (!isset($customFieldsProductLevel->{$customField['body']['field_name']}))
                       $customFieldsProductLevel->{$customField['body']['field_name']} = !empty($value['field_value'])?$value['field_value']:$customFieldsProductLevel->{$customField['body']['field_name']};
                }

            }
            // if sku has custom fields
            // if (!empty((array) $customFieldsSkuLevel))
               $sku->custom_fields = !empty((array) $customFieldsSkuLevel)?$customFieldsSkuLevel : null;
        }

        // if product has custom fields
        // if (!empty((array) $customFieldsProductLevel))
            $product->custom_fields = !empty((array) $customFieldsProductLevel) ? $customFieldsProductLevel : null;
        
        return response()->json([
            'code' => config('globals.status_code.OK_STATUS'),
            'product' => $product
        ]);
    }

    public function store()
    {
        // return response()->json($this->product->create(\Input::all()));
    }

    public function update($id)
    {
        // return response()->json($this->product->update(\Input::all(), $id));
    }

    public function destroy($id)
    {
        // return response()->json($this->product->delete($id));
    }
}
