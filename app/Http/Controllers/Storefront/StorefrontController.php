<?php

namespace App\Http\Controllers\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Repositories\ChannelRepository as ChannelRepo;
use App\Repositories\SalesRepository as SalesRepo;
use App\Repositories\ProductRepository as ProductRepo;
use App\Repositories\Criteria\Products\ByChannel;
use Cache;

class StorefrontController extends Controller
{
    private $oauth;
    private $channel;
    private $sales;
    private $product;

    
    public function __construct(Request $request, ChannelRepo $channelRepo)
    {
        /**
    	*	Authenticate who's accessing. (Channel, HubwireAdmin, )	
    	**/
        $this->oauth = \OAuthClient::find(\Authorizer::getResourceOwnerId());
        // \Log::info(print_r($this->oauth->authenticatable_type, true));
        $this->channel = $channelRepo->getByToken();
    }

    public function index()
    {
        return response()->json($this->oauth->authenticatable);
    }

    public function productIndex(ProductRepo $productRepo)
    {
        $productRepo->pushCriteria(new ByChannel($this->channel->channel_id));
        
        $products = $productRepo->with('sku_in_channel', 'media')->skip(\Input::get('start', 0))->take(\Input::get('size', 10))->all();
        return response()->json(['products'=> $products ]);
    }

    public function getProductById(ProductRepo $product, $product_id)
    {
        return response()->json($product->with('sku_in_channel', 'media')->find($product_id));
    }

    public function getSKUById(ProductRepo $product, $sku_id)
    {
        return response()->json($product->with('sku')->find($product_id));
    }

    public function getSaleById(SalesRepo $sale, $sale_id)
    {
        return response()->json($sale->with('items', 'member', 'notes', 'status_log')->find($sale_id));
    }

    public function salesIndex(SalesRepo $sales)
    {
        return response()->json(['sales'=> $sales->take(100)->all()]);
    }

    public function createSales(SalesRepo $sales)
    {
        $inputs = \Input::all();
        $inputs['channel_id'] = $this->channel->channel_id;
        $inputs['client_id'] = $this->channel->client_id;
        return response()->json($sales->create($inputs));
    }

    public function updateSale(SalesRepo $sales, $sale_id)
    {
        return response()->json($sales->update(\Input::all(), $sale_id));
    }
}
