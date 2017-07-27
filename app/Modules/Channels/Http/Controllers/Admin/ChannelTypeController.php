<?php

namespace App\Modules\Channels\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Modules\Channels\Repositories\Contracts\ChannelTypeRepositoryContract as ChannelTypeRepository;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;
use App\Models\Admin\ChannelType;
use App\Modules\Channels\Repositories\Criteria\ByManualOrder;

class ChannelTypeController extends AdminController
{
    protected $channelTypeRepo;

    protected $authorizer;

    public function __construct(ChannelTypeRepository $channelTypeRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->channelTypeRepo = $channelTypeRepo;
        $this->authorizer = $authorizer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $channelTypes = $this->channelTypeRepo->with('channels')->all();

        return response()->json($channelTypes);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $channelType = $this->channelTypeRepo->create($request->all());
        Activity::log('New channel type (' . $channelType->name . ' - ' . $channelType->id . ') has been created.', $this->authorizer->getResourceOwnerId());

        return response()->json($channelType);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $channelType = $this->channelTypeRepo->with('channels')->findOrFail($id);

        return response()->json($channelType);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $channelType = $this->channelTypeRepo->update($request->all(), $id);
        Activity::log('Channel type ' . $id . ' has been updated.', $this->authorizer->getResourceOwnerId());

        return response()->json($channelType);
    }

    public function updateStatus(Request $request, $id) {
        $status = $request->input('status');
        $response = $this->channelTypeRepo->updateStatus($id, $status);

        if ($response) {
            if ($status == 'Inactive') {
                Activity::log('Channel type ' . $id . ' and its channels has been deactivated.', $this->authorizer->getResourceOwnerId());
            }
            else {
                Activity::log('Channel type ' . $id . ' has been activated.', $this->authorizer->getResourceOwnerId());
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $response = $this->channelTypeRepo->delete($id);

        if ($response == 1) {
            Activity::log('Channel type ' . $id . ' has been deleted.', $this->authorizer->getResourceOwnerId());
        }

        return response()->json(['response' => ($response == 1) ? true : $response]);
    }

    public function getOutdatedCategoriesProducts($id)
    {
        $products = ChannelType::select('merchants.id as merchant_id', 'merchants.name as merchant_name', 'products.id as product_id', 'products.name as product_name', 'products.brand as brand_name', 'tpcat.cat_id as category_id', 'tpcat.cat_name as category_name')
                        ->join('channels', 'channels.channel_type_id', '=', 'channel_types.id')
                        ->join('channel_sku', 'channel_sku.channel_id', '=', 'channels.id')
                        ->join('merchants', 'channels.merchant_id', '=', 'merchants.id')
                        ->join('products', 'channel_sku.product_id', '=', 'products.id')
                        ->join('product_third_party_categories as tpcat', 'channel_sku.product_id', '=', 'tpcat.product_id')
                        ->where('channel_types.id', $id)
                        ->groupBy('channel_sku.product_id')
                        ->get();

        return response()->json($products);
    }

    public function getMOEnabledChannelTypes()
    {
        $this->channelTypeRepo->pushCriteria(new ByManualOrder(1));

        return response()->json($this->channelTypeRepo->all());
    }

    public function getBulkChannelTypes(Request $request)
    {
        $channelTypes = $this->channelTypeRepo->whereIn('id', $request->get('channel_type_id'));

        return response()->json($channelTypes);
    }

    public function getManifestActiveChannels() {
        return response()->json($this->channelTypeRepo->getManifestActiveChannels());
    }
}
