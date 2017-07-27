<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Repository as Repository;
use App\Models\Admin\GTOManifest;
use App\Models\Admin\GTOItem;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\DeliveryOrderItem;
use App\Models\Admin\OrderStatusLog;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\SKU;
use App\Models\Admin\RejectLog;
use Carbon\Carbon;
use Activity;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Event;
use App\Events\OrderUpdated;
use App\Repositories\StockTransferRepository;

class GTOManifestRepository extends Repository
{
    protected $model;
    protected $user_id;
    protected $skipCriteria = true;
    static $NUM_OF_ORDERS = 20;

    public function __construct()
    {
        parent::__construct();
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    public function model()
    {
        return 'App\Models\Admin\GTOManifest';
    }

    public function search($request)
    {
        $type = $request->get('type',0);
        // separate query for counting number of manifests
        $count = \DB::select(\DB::raw("select count(*) as total from gto_manifests"));

        $manifests = GTOManifest::leftjoin('users as user', 'gto_manifests.user_id', '=', 'user.id')
                    ->leftjoin('users as creator', 'gto_manifests.creator_id', '=', 'creator.id')
                    ->select('gto_manifests.*', 'user.first_name as user_name', 'creator.first_name as creator_name')
                    ->skip($request->input('start', 0))
                    ->take($request->input('length', 10));

        if ($request->input('order'))
        {
            $colNum = $request->input('order')[0]['column'];
            $direction = null;
            if ($request->input('order')[0]['dir'] == 'desc')
            {
                $direction = 'desc';
            }else
            {
                $direction = 'asc';
            }
            $colName = $request->input("columns")[$colNum]['name'];
            $manifests = $manifests->orderBy($colName, $direction);
        }
        else{
            $manifests = $manifests->orderBy('created_at', 'desc');
        }
        $manifests = $manifests->get();

        $response['success'] = true;
        $response['manifests'] = $manifests->toArray();
        $response['recordsFiltered'] = $count[0]->total;
        $response['recordsTotal'] = $count[0]->total;

        return $response;
    }

	public function generateManifest($do_id) 
    {
        $items = DeliveryOrderItem::setEagerLoads([])->where('do_id',$do_id)->get();
        // Generate picking manifest
        $manifestID = \DB::table('gto_manifests')->insertGetId(['do_id'=>$do_id,'user_id' => 0, 'status' => 'New', 'creator_id' => $this->user_id, 'created_at' => date("Y-m-d H:i:s"), 'updated_at' => date("Y-m-d H:i:s")]);

        $orderItems = $items->groupBy('sku_id');
        
        // Generate picking items
        foreach($orderItems as $item) {
            \DB::table('gto_manifest_items')->insert(['gto_id' => $manifestID, 'sku_id' => $item[0]->sku_id, 'quantity'=>$item->sum('quantity'), 'picked'=>0, 'created_at' => date("Y-m-d H:i:s"), 'updated_at' => date("Y-m-d H:i:s")]);
        }

        $response['success'] = true;
        $response['message'] = "Picking manifest generated.";
        Activity::log('Manifest ('.$manifestID.') was generated.', $this->user_id);
        return $response;
    }

    // assign manifest to self
    public function pickUpManifest($request) {
        $id = $request->input('id');
        $manifest = GTOManifest::find($id);
        $data['success'] = false;
        if (!is_null($manifest) && $manifest->user_id==0) {
            $manifest->user_id = $this->user_id;
            $manifest->status = 'In Progress';
            $manifest->pickup_date = date("Y-m-d H:i:s");
            $manifest->save();
            $data['success'] = true;

            Activity::log('Manifest ('.$id.') was picked up.', $this->user_id);
        }

        return $data;
    }

    // show picking items
    public function pickingItems($id) {
        //return GTOItem::where('gto_id','=',$id)->get();
        return \DB::table('gto_manifest_items')->select('gto_manifest_items.id', 'gto_manifest_items.quantity', 'gto_manifest_items.picked', 'sku.hubwire_sku', 'products.name', 
                'channel_sku.channel_sku_coordinates', 'gto_manifests.created_at','sku.hubwire_sku','sku.sku_id' )
                ->leftjoin('gto_manifests', 'gto_manifests.id', '=', 'gto_manifest_items.gto_id')
                ->leftJoin('delivery_orders','delivery_orders.id','=','gto_manifests.do_id')
                ->leftjoin('sku', 'gto_manifest_items.sku_id', '=', 'sku.sku_id')
                ->leftJoin('channel_sku',function($join){
                    $join->on('channel_sku.sku_id','=','sku.sku_id');
                    $join->on('channel_sku.channel_id','=','delivery_orders.target_channel_id');
                })
                ->leftjoin('products', 'products.id', '=', 'sku.product_id')
                ->where('gto_id', '=', $id)
                ->groupBy('gto_manifest_items.id')
                ->get();
        
    }
    
    public function getUniqueOrders($id) {
        $manifest = GTOManifest::find($id);
        $orders = DeliveryOrder::where('id',$manifest->do_id)
                ->get();

        return $orders;
    }

    // function for handling scanning hubwire sku
    public function pickItem($request, $id) 
    {    
        $hubwireSku = trim($request->input('hubwire_sku'));
        
        $item = GTOItem::select('gto_manifests.user_id', 'gto_manifest_items.picked', 
            'gto_manifest_items.quantity', 'gto_manifests.status', 'gto_manifest_items.id', 'sku.hubwire_sku', 'products.name')
            ->leftjoin('gto_manifests', 'gto_manifests.id', '=', 'gto_manifest_items.gto_id')
            ->leftjoin('delivery_orders', 'delivery_orders.id', '=', 'gto_manifests.do_id')
            ->leftjoin('channel_sku', function($join){
                $join->on('channel_sku.sku_id', '=', 'gto_manifest_items.sku_id');
                $join->on('channel_sku.channel_id','=','delivery_orders.target_channel_id');
            })
            ->leftjoin('sku', 'channel_sku.sku_id', '=', 'sku.sku_id')
            ->leftjoin('products', 'products.id', '=', 'sku.product_id')
            ->where('sku.hubwire_sku', '=', $hubwireSku)
            ->where('gto_manifest_items.gto_id', '=', $id)
            ->whereRaw('gto_manifest_items.picked != gto_manifest_items.quantity')
            ->orderBy('channel_sku.channel_sku_coordinates', 'desc')
            ->orderBy('sku.sku_id', 'desc')
            ->orderBy('delivery_orders.id', 'desc')
            ->first();

        
        $data = array();
        $data['success'] = true;
        $data['readyToComplete'] = false;

        if (!is_null($item) && trim($item->hubwire_sku)==trim($hubwireSku)) {
            // ensure the user picking this item is assigned to this manifest
            if ($item->user_id==$this->user_id) {
                // ensure order hasn't been cancelled
                if ($item->status=='Cancelled') {
                    $data['message'] = "The manifest for this item has been cancelled.";
                    $data['success'] = false;
                }
                else if ($item->picked == $item->quantity) {
                    $data['message'] = "This picking manifest has been marked completed.";
                    $data['success'] = false;
                }
                else {
                
                    $item->increment('picked');
                    $item->save();
                    $data['orderItemId'] = $item->id;
                    $data['message'] = "Success!";
                    $data['readyToComplete'] = $this->isReadyToComplete($id);               
                }           
            }
            else if ($item->user_id==0) {
                $data['message'] = "This manifest has not been picked up by anyone. You must pick up this manifest to begin picking items.";
                $data['success'] = false;
            }
            else {
                $data['message'] = "You are not authorized to pick items for this picking manifest.";
                $data['success'] = false;
            }
        }
        else {
            $sku = SKU::select('products.name')->leftjoin('products', 'products.id', '=', 'sku.product_id')->where('sku.hubwire_sku','=',$hubwireSku)->first();
            $productName = (is_null($sku))?'':"-".$sku->product_name;
            
            $data['message'] = "The item ".$hubwireSku.$productName." was not found on the list, has already been picked, or has been marked 'Out of Stock'.";
            $data['success'] = false;
        }
        return $data;
    }

    // to inform the system that the picking item is out of stock
    public function outOfStock($request, $id) {
        $item = GTOItem::where('id', '=', $request->input('id'))->with('manifest')->first();
        $data['success'] = false;
        $data['readyToComplete'] = false;
        
        if ($item->gto_id==$id && $item->manifest->user_id==$this->user_id) { 

            $orderItem = DeliveryOrderItem::find($item->item_id);
            
            if (!is_null($orderItem)) {
                $oldStatus = $orderItem->picking_status;
                $orderItem->picking_status = "Out of Stock" ;
                $orderItem->save();

                // Event::fire(new OrderUpdated($orderItem->order_id, 'Item Status Updated', 'order_items', $orderItem->id, array('fromStatus' => $oldStatus, 'toStatus' => $orderItem->status), $this->user_id));

                $data['orderItemId'] = $orderItem->id;
                $data['success'] = true;
                $data['readyToComplete'] = $this->isReadyToComplete($id);

                Activity::log('Order item ('.$data['orderItemId'].') was marked out of stock.', $this->user_id);
            } 
        }
        else if ($item->manifest->user_id==0) {
            $data['message'] = "This manifest has not been picked up by anyone. You must pick up this manifest to update it.";
        }
        else if ($item->manifest->user_id!=$this->user_id) {
            $data['message'] = "You are not authorized to update this picking manifest.";
        }
        return $data;
    }

    // Mark manifest as complete
    public function completed($id) {
        $user = $this->user_id;
        $manifest = GTOManifest::where('id', '=', $id)->where('status', '=', 'In Progress')->first();
        $data['success'] = false;
        if (!is_null($manifest)) {
            // if ($user->can('complete.stockout.manifest')) {
            //     $data['message'] = "You are not authorized to update this picking manifest.";
            // }
            // else 
            if (!$this->isReadyToComplete($manifest->id)) {
                $data['message'] = "This manifest is not ready to be completed. Please pick all items before proceeding.";
            }
            else {
                
                $items = $manifest->pickingItem()->get();
                $do = DeliveryOrder::find($manifest->do_id);
                foreach($items as $item)
                {
                    if($item->picked !== $item->quantity && $item->picked > 0)
                    {
                        $reject = new RejectLog;
                        $reject->user_id = $this->user_id;
                        $reject->sku_id = $item->sku_id;
                        $reject->channel_id = $do->target_channel_id;
                        $reject->quantity = $item->picked;
                        $reject->remarks = 'Stock Out';
                        $reject->outbound = 1;
                        $reject->save();

                        $reject = new RejectLog;
                        $reject->user_id = $this->user_id;
                        $reject->sku_id = $item->sku_id;
                        $reject->channel_id = $do->target_channel_id;
                        $reject->quantity = $item->quantity - $item->picked;
                        $reject->remarks = 'Out of Stock';
                        $reject->outbound = 1;
                        $reject->save();

                    }
                    else
                    {
                        $reject = new RejectLog;
                        $reject->user_id = $this->user_id;
                        $reject->sku_id = $item->sku_id;
                        $reject->channel_id = $do->target_channel_id;
                        $reject->quantity = $item->quantity;
                        $reject->remarks = $item->picked==$item->quantity?'Stock Out':'Out of Stock';
                        $reject->outbound = 1;
                        $reject->save();                        
                    }
                    
                }
                // update the Delivery Order Related
                DeliveryOrderItem::where('do_id','=',$do->id)->update(['status'=>1]);
                $do->receive_at = date('Y-m-d H:i:s');
                $do->status = 2;
                $do->save();

                $manifest->status = 'Completed';
                $manifest->touch();
                $manifest->save();
                $data['success'] = true;
                Activity::log('Manifest ('.$id.') was completed.', $this->user_id);
            }
        }
        return $data;
    }

    public function cancel($id)
    {
        $manifest = GTOManifest::where('id', '=', $id)->whereIn('status', ['In Progress','New'])->first();
        $data['success'] = false;
        if (!is_null($manifest)) {
            
                $item = GTOItem::where('gto_id','=',$id)->first();
                $do_id = $item->manifest->do_id;
                $stockTransfer = new StockTransferRepository;
                $stockTransfer->receiveStockTransfer($do_id);

                $manifest->status = 'Cancelled';
                $manifest->touch();
                $manifest->save();

                $data['success'] = true;
            
        }
        return $data;
    }

    // check if picking manifest is ready to be completed
    public function isReadyToComplete($id) {
        return true;
    }

    public function assignUser($data, $id)
    {
        $manifest = $this->find($id);
        // \Log::info($manifest->toArray());
        if($manifest->status == 'New'){
            // update pickup date
            $data['pickup_date'] = date("Y-m-d H:i:s");
            $data['status'] = 'In Progress';
        }
        // \Log::info($data);
        $model = parent::update($data, $id);

        return $this->find($id);
    }
}
