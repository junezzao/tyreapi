<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\SalesRepository as SalesRepo;
use App\Repositories\SalesItemRepository as ItemRepo;
use App\Repositories\MemberRepository as MemberRepo;
use App\Repositories\ChannelSKURepository as ChannelSKURepo;
use App\Repositories\Criteria\Sales\ByChannel;
use App\Repositories\Criteria\Sales\ByClient;
use App\Repositories\Criteria\Sales\ByStatus;
use App\Repositories\Criteria\Sales\CreatedAt;
use App\Repositories\Criteria\Sales\SinceId;
use App\Repositories\Criteria\Sales\UpdatedAt;
use App\Repositories\Criteria\Sales\WithChanges;
use Illuminate\Support\Collection;
use Illuminate\Container\Container as App;
use App\Modules\ThirdParty\Http\Controllers\OrderProcessingService as OrderProc;
use App\Events\ChannelSkuQuantityChange;
use Cache;

class SalesController extends Controller
{

    private $sales;
    private $oauth;
    private $inputs;


    public function __construct(SalesRepo $sales)
    {
        $this->sales = $sales;
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        $this->inputs = \Input::all();
        unset($this->inputs['access_token']);
        unset($this->inputs['HTTP_Authorization']);

        if ($this->oauth->authenticatable_type === 'Channel') {
            $this->inputs['channel_id'] = $this->oauth->authenticatable_id;
            // $this->inputs['client_id'] = $this->oauth->authenticatable->client_id;
            $this->sales->pushCriteria(new ByChannel($this->inputs['channel_id']));
        }
    }

    public function index()
    {
        $rules = [
            'status' => 'sometimes|required|string',
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
        if (!empty($this->inputs['status'])) {
            $this->sales->pushCriteria(new ByStatus($this->inputs['status']));
        }
        if (!empty($this->inputs['created_at'])) {
            $this->sales->pushCriteria(new CreatedAt($this->inputs['created_at']));
        }
        if (!empty($this->inputs['sinceid'])) {
            $this->sales->pushCriteria(new SinceId($this->inputs['sinceid']));
        }
        if (!empty($this->inputs['updated_at'])) {
            $this->sales->pushCriteria(new UpdatedAt($this->inputs['updated_at']));
        }
        if (isset($this->inputs['changed'])) {
            $this->sales->pushCriteria(new WithChanges($this->inputs['changed']));
        }
        $sales = $this->sales->with('items', 'member');
        $data  = $sales->skip(\Input::get('start', 0))->take(\Input::get('limit', 50))->all() ;
        // \Log::info(print_r($data, true));
        return response()->json([
            'start'=> intval(\Input::get('start', 0)),
            'limit' => intval(\Input::get('limit', 50)),
            'total' => $sales->count(),
            'sales'=> $data
        ]);
    }

    public function show($id)
    {
        return response()->json(\Sales::apiResponse($this->sales->with('items', 'member')->findOrFail($id)));
    }

    public function store(App $app, Collection $collection, MemberRepo $memberRepo)
    {
        /* Not in used - storefront discontinued
        // Create member
        if (!empty($this->inputs['customer'])) {
            $this->inputs['customer']['channel_id'] = $this->inputs['channel_id'];
            $this->inputs['customer']['client_id'] = $this->inputs['client_id'];

            $member = $memberRepo->findBy('member_email', $this->inputs['customer']['email']);
            if (empty($member)) {
                $memberRepo = new $memberRepo($app, $collection);
                $member = $memberRepo->create($this->inputs['customer']);
            } else {
                $memberRepo->update($this->inputs['customer'], $member->member_id);
            }
            $this->inputs['customer_id'] = $member->member_id;
        }
        $sale = $this->sales->create($this->inputs);
        // Create Items
        foreach ($this->inputs['items'] as $data) {
            $data['sale_id'] = $sale->sale_id;
            $data['product_type'] = 'ChannelSKU';
            $data['channel_id'] = $data['decremented_from'] = $this->inputs['channel_id'];
            $item = new ItemRepo($app, $collection);
            $sale_item = $item->create($data);
            $channel_repo = new ChannelSKURepo;
            $channel_sku = $channel_repo->findOrFail($sale_item->product_id);
            $oldQuantity = $channel_sku->channel_sku_quantity;
            $channel_sku->decrement('channel_sku_quantity', $data['quantity']);
            event(new ChannelSkuQuantityChange($channel_sku->channel_sku_id, $oldQuantity, 'SaleItem', $sale_item->id));

        }
        $sale = $this->sales->with('items', 'member')->findOrFail($sale->sale_id);
        if($sale->paid_status == 1) {
            $order_proc = new OrderProc();
            $order_proc->incrementReservedQuantity($sale->sale_id);
        }
        return response()->json(\Sales::apiResponse($sale));
        */
    }

    public function update($id)
    {
        // $sale = $this->sales->update($this->inputs, $id);
        // return response()->json(\Sales::apiResponse($sale));
    }

    public function destroy($id)
    {
        // return response()->json($this->sales->delete($id));
    }
}
