<?php

namespace App\Modules\Products\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use App\Models\Admin\DeliveryOrder;
use App\Models\Admin\DeliveryOrderItem;
use Bican\Roles\Models\Role;
use App\Repositories\StockTransferRepository;
use App\Repositories\Eloquent\SyncRepository;
use Activity;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Event;
use App\Events\StockTransferReceived;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Merchant;
use App\Models\Admin\Channel;
use Cache;


class StockTransferController extends Controller
{
    //
    protected $stockTransferRepo;
    protected $authorizer;
    protected $userRepo;

    public function __construct(StockTransferRepository $stockTransferRepo, Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->stockTransferRepo = $stockTransferRepo;
        $this->authorizer = $authorizer;
    }

	public function index()
	{
		$stockTransfers = $this->stockTransferRepo->all(request()->all());
		return response()->json([
			'start'=> intval(\Input::get('start', 0)),
            'limit' => intval(\Input::get('limit', 30)),
            'total' => \DB::table('delivery_orders')->count(),
            'stockTransfers'=>$stockTransfers
        ]);
    }

    public function show($id)
    {
        $stockTransfer = DeliveryOrder::with('originating_channel', 'target_channel', 'merchant','manifest')->findOrFail($id);

        $items = DeliveryOrderItem::with('channel_sku')->where('do_id',$id)->get();
        if($stockTransfer->do_type==2)
        {
        	$tmp = [];
        	foreach($items->groupBy('channel_sku_id') as $k => $v)
        	{
        		$v[0]->quantity = $v->sum('quantity');
        		$tmp[$k] = $v[0];
        	}
        	$items = $tmp;
        }
        return response()->json(['stockTransfer' => $stockTransfer, 'items'=>$items]);
    }

    // save stock transfer as draft
	public function store(Request $request)
	{
		$inputs = $request->all();

		// create stock transfer
		$stockTransfer = $this->stockTransferRepo->createStockTransfer($inputs);

		// if user clicked 'create and initiate stock transfer'
		if ($inputs['do_status']==1 && $inputs['do_type'] != 2) {
			$this->initiateTransfer($stockTransfer->id);
		}

		return response()->json(['success'=>true, 'stockTransfer'=>$stockTransfer]);
	}

	public function update(Request $request, $id)
	{
		$inputs = $request->all();

		$stockTransfer = $this->stockTransferRepo->updateStockTransfer($inputs, $id);

		// if user clicked 'create and initiate stock transfer'
		if ($inputs['do_status']==1) {
			$this->initiateTransfer($stockTransfer->id);
		}

		return response()->json(['success'=>true]);
	}

	public function initiateTransfer($id)
	{
		$response = $this->stockTransferRepo->initiateTransfer($id);
		$success = false;

		if (isset($response['stockTransfer']->status) && $response['stockTransfer']->status==1) {
			$success = true;
			//Event::fire(new StockTransferReceived($response['skus']));

			$syncRepo = new SyncRepository;
			$sync = $syncRepo->stockTransfer($id);
		}

		return response()->json(['success' => $success]);
	}

	// receive stock transfer
	public function receiveStockTransfer($id)
	{
		$response = $this->stockTransferRepo->receiveStockTransfer($id);
		$success = false;

		if (isset($response['stockTransfer']->status) && $response['stockTransfer']->status==2) {
			$success = true;
			//Event::fire(new StockTransferReceived($response['skus']));

			$syncRepo = new SyncRepository;
			$sync = $syncRepo->stockTransfer($id);
		}

		return response()->json(['success' => $success]);
	}

	public function destroy($id)
	{
		$ack = $this->stockTransferRepo->deleteStockTransfer($id);

		return response()->json(['acknowledged' => $ack ? true : false]);
	}

	public function processSKU($merchant_id)
	{
		$inputs = request()->all();
		//\Log::info(print_r($inputs, true));
		$data = $this->csv_to_array($inputs['tfile'],',',',');
		//\Log::info(print_r($data['items'], true));
		
		$data['merchant_id'] = $merchant_id;
		$rules['merchant_id'] = 'required|exists:merchants,id';
		$messages = array();
		$skus = array();
		if(!empty($data['items']))
		{
			foreach($data['items'] as $k => $v)
			{
				$rules["items.$k.hubwire_sku"] = 'required|exists:sku,hubwire_sku,merchant_id,'.$merchant_id;
				$rules["items.$k.channel_name"] = 'required|exists:channels,name';
				$messages["items.$k.hubwire_sku.exists"] = '<b>'.$v['hubwire_sku'] .'</b> is invalid hubwire sku.';
                $messages["items.$k.channel_name.exists"] = "<b>".$v['channel_name'] .'</b> is invalid channel name.';
				$sku = SKU::where('hubwire_sku','=',$v['hubwire_sku'])->first();
				$channel = Channel::where('name','=',$v['channel_name'])->first();
				if(!is_null($channel) && !is_null($sku))
				{
					$channel_sku = ChannelSKU::with('channel','sku_options')->where('sku_id','=',$sku->sku_id)
								->where('channel_id','=',$channel->id)->first();
					$max_qty = !is_null($channel_sku)?$channel_sku->channel_sku_quantity:0;
					$rules["items.$k.quantity"] = 'required|integer|min:1|max:'.$max_qty;
					$messages["items.$k.quantity.max"] = '<b>'.$v['hubwire_sku'] .' ('.$v['channel_name'].')</b> maximum quantity is <b>'.$max_qty.'</b>.';
					if(!empty($channel_sku))
					{
	                    $tmp = $channel_sku->toArray();
						$tmp['quantity'] = $v['quantity'];
						if(!is_null($channel_sku)) $skus[] = $tmp;
					}
				}
			}
		}
		$v = \Validator::make($data, $rules, $messages);
		if($v->fails())
		{
			return response()->json(['success' => false, 'error' => ['messages' => $v->errors()] ] );
		}
		return response()->json(['success' => true, 'skus'=>$skus  ]);	
	}

	public function manifest($id)
	{
		$manifest = $this->stockTransferRepo->manifest($id);
		return response()->json($manifest);
	}

	public function csv_to_array($filename='', $delimiter1='|', $delimiter2= ',')
    {
        ini_set('auto_detect_line_endings', true);
        if(!file_exists($filename) || !is_readable($filename))
            return FALSE;
        $header = config('csv.upload');
        $fields = config('csv.create');
        $mydata = array();
        $data = array();
        $mydata['ok'] = true;
        if (($handle = fopen($filename, 'r')) !== FALSE)
        {
            $test = fgetcsv($handle, 1000, $delimiter1);
            $test = array_filter($test,'strlen');
            if(count($test) != 3 )
            {
                $mydata['messages'][] = 'Invalid template format. Please use the template provided.';
                $mydata['ok'] = false;
                $mydata['count'] = count($test);
                return $mydata;
            }
            //REPLENISHMENT
            if(count($test) == 3)
            {
                $flag = true;
                while (($row = fgetcsv($handle, 1000, $delimiter1)) !== FALSE)
                {
                    $chk = array_filter($row,'strlen');
                    if(empty($chk)){
                        continue;
                    }
                    $data[] = array(
                        'hubwire_sku'=>$row[0],
                        'channel_name'=>$row[1],
                        'quantity'=>$row[2]
                    );
                }
                $mydata['type']     = "stock-out";
            }
            fclose($handle);
            $mydata['items'] = $data;
            return $mydata;
        }
    }
}
