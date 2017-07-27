<?php

namespace app\Repositories;

use App\Repositories\ProductRepository as ProductRepo;
use App\Models\Admin\Channel;
use App\Models\Admin\Webhook;
use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use App\Repositories\SalesRepository as SalesRepo;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;

// use App\Models\Channel;

class ChannelRepository extends Repository
{
    protected $model;

    protected $purchaseRepo;

    protected $role;

    protected $skipCriteria = false;

    public function __construct(Channel $model)
    {
        $this->model = $model;
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\Channel';
    }
    
    public function getByToken()
    {
        $oauth = \OAuthClient::find(Authorizer::getResourceOwnerId());
        return $oauth->authenticatable;
    }

    public function getChannelByType($type)
    {
        $channel = Channel::where('channel_type', '=', $type)->get();
        return $channel;
    }

    public function getChannelByDomain($domain)
    {
        // \Log::info($domain);
        return Channel::with('channel_details')->where('channel_web', '=', $domain)->firstOrFail();
    }

    public function getChannelById($ch_id)
    {
        $channel = Channel::findOrFail($ch_id);
        return $channel;
    }

    public function getMenu($channel_id)
    {
        $menu = \Menu::where('channel_id', '=', $channel_id)->firstOrFail();
        $menus = json_decode($menu->menu_content);
        // \Log::info($menus);
        $response = new \stdclass();
        foreach ($menus as $m) {
            $item = new \stdclass();
            $item->text = $m->text;
            $item->href = $m->a_attr->{'data-href'};
            $item->tags = $m->a_attr->{'data-tags'};
            $item->icon = !empty($m->a_attr->{'data-icon'})?$m->a_attr->{'data-icon'}:'';
            $item->banner = !empty($m->a_attr->{'data-banner'})?$m->a_attr->{'data-banner'}:'';
            $response->menus[] = $item;
        }
        return $response;
    }

    public function getFilters($client_id)
    {
        $response = new \stdClass();
        $filter = new \stdClass();
        // get all options and possible values
        $filter->name = 'options';
        $filter->type = 'list';
        $values = $this->getOptions($client_id);
        $tmp = array();
        foreach ($values as $option) {
            $tmp[$option->option_name][] = $option->option_value;
        }
        $filter->values = $tmp;
        $response->filters[] = $filter;

        // get all brands


        return $response;
    }

    public function getOptions($client_id)
    {
        // Get the options
        $options = \SKUOption::
                            join('sku_combinations', 'sku_options.option_id', '=', 'sku_combinations.option_id')
                            ->join('sku', 'sku_combinations.sku_id', '=', 'sku.sku_id')
                            ->where('client_id', '=', $client_id)
                            ->select('sku_options.option_id', 'option_name', 'option_value')
                            ->groupBy('sku_options.option_id')
                            ->orderBy('sku_options.option_name')
                            ->orderBy('sku_options.option_value')
                            ->get();
        return $options;
    }

    public function getChannelProducts($ch_id)
    {
        return $this->productAPIResponse(\ChannelSKU::where('channel_id', '=', $ch_id)
                ->where('channel_sku_active', '=', 1)
                ->get());
    }
    
    public function getSales($ch_id, $filters)
    {
        $filters['from'] = !empty($filters['from'])?$filters['from']:0;
        $filters['size'] = !empty($filters['size'])?$filters['from']:1000;
        return \Sales::where('channel_id', '=', $ch_id)->with('items')
                ->skip($filters['from'])
                ->take($filters['size'])
                ->get();
    }

    public function getSaleById($ch_id, $sale_id)
    {
        return \Sales::where('channel_id', '=', $ch_id)
                ->with('items', 'member', 'notes', 'status_log')
                ->findOrFail($sale_id);
    }

    public function createSale($ch_id, $inputs)
    {
        $saleRepo = new SalesRepo;
        $channel  = $this->getChannelById($ch_id);
        $inputs['channel_id'] = $ch_id;
        $inputs['client_id'] = $channel->client_id;
        return $saleRepo->create($inputs);
    }

    public function updateSale($ch_id, $sale_id, $inputs)
    {
        $saleRepo = new SalesRepo;
        $channel  = $this->getChannelById($ch_id);
        $inputs['channel_id'] = $ch_id;
        $inputs['client_id'] = $channel->client_id;
        return $saleRepo->update($inputs, $sale_id);
    }

    public function getProducts(ProductRepo $product, $ch_id)
    {
        $channel = $this->getChannelById($ch_id);
        $params['columns']['channel_id'] = $ch_id;
        $params['columns']['client_id'] = $channel->client_id;
        $params['columns']['product_name'] = " ";
        return $product->searchDB2($params);
    }

    public function getChannelProduct($ch_id, $sku_id)
    {
        return $this->productAPIResponse([\ChannelSKU::where('channel_id', '=', $ch_id)->where('channel_sku_active', '=', 1)->where('sku_id', '=', $sku_id)->firstOrFail()]);
    }

    public function productAPIResponse($channel_skus)
    {
        $response = new \stdClass();
        if (!empty($channel_skus)) {
            foreach ($channel_skus as $channel_sku) {
                $product = new \stdClass();
                $product->sku_id = $channel_sku['sku_id'];
                $product->product_name = addslashes($channel_sku['product']['product_name']);
                $product->hubwire_sku = $channel_sku->sku->hubwire_sku;
                $product->quantity = $channel_sku->channel_sku_quantity;
                $product->barcode = $channel_sku->sku->sku_barcode;
                $product->weight = $channel_sku->sku->sku_weight;
                $response->products[] = $product;
            }
        }
        return $response;
    }

    public function replenishment($channel_id, $sku_list, $dc_id)
    {
        $this->validateReplenishSKU($sku_list, $channel_id);
        $channel = \Channel::findOrFail($channel_id);
        $client_id = $channel->client_id;
        \DB::beginTransaction();
        $batch = new \Purchase;
        $batch->batch_date = date('Y-m-d');
        $batch->batch_status = 0;
        $batch->client_id = $client_id;
        $batch->channel_id = $channel_id;
        $batch->batch_remarks = 'Partner Replenishment';
        $batch->save();
        foreach ($sku_list as $sku) {
            $item = new \PurchaseItem;
            $item->batch_id = $batch->batch_id;
            $item->item_replenishment = 1;
            $item->item_status = 0;
            $item->item_quantity = $sku['quantity'];
            $item->sku_id = $sku['sku_id'];
            $item->item_cost = 0.00;
            $item->item_retail_price = 0.00;
            $item->save();
        }
        \DB::commit();
        return $this->replenishAPIResponse($batch->batch_id, $dc_id);
    }

    public function replenishAPIResponse($batch_id, $dc_id = null)
    {
        $response = new \stdClass();
        $batch = \Purchase::with('items')->findOrFail($batch_id);
        $response->replenish_id = $batch->batch_id;
        $response->replenish_date = $batch->batch_date;
        $response->client_id = $batch->client_id;
        $response->replenish_status = $batch->batch_status;
        if (!empty($dc_id)) {
            $response->distribution_center_id = $dc_id;
        } else {
            $response->channel_id = $batch->channel_id;
        }
        foreach ($batch->items as $item) {
            $tmp = new \stdClass();
            $tmp->sku_id = $item->sku_id;
            $tmp->product_name = $item->sku->product->product_name;
            $tmp->barcode = $item->sku->sku_barcode;
            $tmp->hubwire_sku = $item->sku->hubwire_sku;
            $tmp->replenish_quantity = $item->item_quantity;
            $tmp->replenish_status = $item->item_status;
            $response->sku_list[] = $tmp;
        }

        return $response;
    }

    public function validateReplenishSKU($sku_list, $ch_id)
    {
        $inputs = ['sku_list' => $sku_list];
        $rules = [
                'sku_list' => 'required|array'
            ];
        
        if (!empty($sku_list) && is_array($sku_list)) {
            foreach ($sku_list as $key => $val) {
                $rules['sku_list.'.$key.'.sku_id'] = 'required|integer|min:1|exists:channel_sku,sku_id,channel_id,'.$ch_id;
                $rules['sku_list.'.$key.'.quantity'] = 'required|integer|min:1';
            }
        }
        $v = \Validator::make($inputs, $rules);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
    }

    public function install_webhook($ch_id, $inputs)
    {
        $inputs['channel_id'] = $ch_id;
        $v = \Validator::make($inputs, [
            'channel_id' => 'required|integer|min:1',
            'event' => 'required|in:'.implode(',', config('partner.webhook_events')),
            'url' => 'required|url'
        ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $webhook = Webhook::firstOrCreate([
                'channel_id'=> $inputs['channel_id'],
                'topic'=>$inputs['event'],
                'address'=>$inputs['url']
                ]);
        return $this->webhookAPIResponse($webhook);
    }

    public function update_webhook($wh_id, $inputs)
    {
        $v = \Validator::make($inputs, [
            'event' => 'required|in:'.implode(',', config('partner.webhook_events')),
            'url' => 'required|url'
        ]);
        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $webhook = Webhook::findOrFail($wh_id);
        $webhook->topic = $inputs['event'];
        $webhook->address = $inputs['url'];
        $webhook->save();
        return $this->webhookAPIResponse($webhook);
    }

    public function get_webhooks($ch_id)
    {
        $webhooks = Webhook::where('channel_id', '=', $ch_id)->get();
        $response = new \stdClass();
        foreach ($webhooks as $webhook) {
            $response->webhooks[] = $this->webhookAPIResponse($webhook);
        }
        return $response;
    }

    public function webhookAPIResponse($webhook)
    {
        $response = new \stdClass();
        $response->webhook_id = $webhook->webhook_id;
        $response->event = $webhook->topic;
        $response->created_at = $webhook->created_at;
        $response->updated_at = $webhook->updated_at;
        $response->url = $webhook->address;
        return $response;
    }
}
