<?php
namespace App\Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Repositories\Contracts\OrderRepository;
// use App\Repositories\Contracts\ManifestRepository;
use App\Repositories\Eloquent\ManifestRepository;
use App\Repositories\Eloquent\GTOManifestRepository;

use App\Http\Traits\DocumentGeneration;
use App\Events\OrderUpdated;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\PickingManifest;
use App\Models\Admin\PickingItem;
use Event;
use Config;
use Excel;
use Activity;

class ManifestController extends Controller
{
    use DocumentGeneration;

	protected $manifestRepo;
	protected $orderRepo;
	protected $authorizer;

	public function __construct(GTOManifestRepository $productManifestRepo,
        ManifestRepository $manifestRepo, 
        OrderRepository $orderRepo, 
        Authorizer $authorizer, 
        Request $request)
	{
		$this->middleware('oauth');
		$this->orderRepo = $orderRepo;
		$this->manifestRepo = $request->get('type')==1?$productManifestRepo:$manifestRepo;
        $this->authorizer = $authorizer;
	}
	public function index() 
	{
		
	}

	// search manifests
    public function search(Request $request) {
        return response()->json($this->manifestRepo->search($request));
    }

	// display picking manifest
	public function show($id)
	{
		$data = array();
        $data['manifest_id'] = $id;
        $data['readyToComplete'] = $this->manifestRepo->isReadyToComplete($id);
        $data['manifest_status'] = $this->manifestRepo->find($id)->status;
        $data['manifest']    = $this->manifestRepo->find($id);
        return response()->json($data);
	}

	// generate picking manifest
	public function store(Request $request) {
		return response()->json($this->manifestRepo->generateManifest($request));
    }
    
    // assign manifest to self
    public function pickUpManifest(Request $request) { 	
        return response()->json($this->manifestRepo->pickUpManifest($request));
    }

    // retrieve all picking items for a manifest
    public function pickingItems($id) {         
        return response()->json($this->manifestRepo->pickingItems($id));
    }

    // get unique orders in a manifest
    public function getUniqueOrders($id) {
        return response()->json($this->manifestRepo->getUniqueOrders($id));
    }

    // function for handling scanning hubwire sku
    public function pickItem(Request $request, $id) {
		return response()->json($this->manifestRepo->pickItem($request, $id));
    }

    // mark picking item as out of stock
    public function outOfStock(Request $request, $id) {
        return response()->json($this->manifestRepo->outOfStock($request, $id));
    }

    // Mark manifest as complete
    public function completed($id) {
    	return response()->json($this->manifestRepo->completed($id));
    }

    // Mark manifest as complete
    public function cancel($id) {
        return response()->json($this->manifestRepo->cancel($id));
    }    

    // returns number of new orders to be picked group by channel type
    public function count()
    {   
        return response()->json($this->manifestRepo->count());
    }

    public function exportPosLaju($id) {
        $poslaju = array('Poslaju', 'Pos Laju');
        $response = array();
        $ordersArray = array();

        // export all that use pos laju
        $orders = PickingItem::select('orders.*', 'issuing_companies.name')
                    ->leftjoin('order_items', 'order_items.id', '=', 'picking_items.item_id')
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                    ->leftjoin('issuing_companies', 'issuing_companies.id', '=', 'channels.issuing_company')
                    ->where('manifest_id', '=', $id)
                    ->where('orders.cancelled_status', '=', 0)
                    ->whereIn('orders.shipping_provider', $poslaju)
                    ->groupBy('orders.id')
                    ->get();

        if (!is_null($orders) && count($orders) > 0) {
            $accounts = Config::get('globals.pos_laju_accounts');
            foreach ($accounts as $account) {
                $account = str_replace(' ','_',$account);
                $ordersArray[$account][] = Config::get('globals.pos_laju_csv_headers');
            }
            $ordersArray['fmhw'][] = Config::get('globals.pos_laju_csv_headers');

            foreach ($orders as $order) {
                if( in_array($order->name, Config::get('globals.pos_laju_accounts')) == true ){
                    $account = str_replace(' ','_',$order->name);
                    $ordersArray[$account][] = array(
                        $order->shipping_recipient,
                        $order->shipping_street_1,
                        $order->shipping_street_2,
                        $order->shipping_postcode,
                        $order->shipping_city,
                        $order->shipping_state,
                        $order->shipping_country,
                        "",
                        $order->shipping_phone,
                        "",
                        "",
                        $order->id,
                        $order->name,
                        $order->id,
                    );
                }else{
                    $ordersArray['fmhw'][] = array(
                        $order->shipping_recipient,
                        $order->shipping_street_1,
                        $order->shipping_street_2,
                        $order->shipping_postcode,
                        $order->shipping_city,
                        $order->shipping_state,
                        $order->shipping_country,
                        "",
                        $order->shipping_phone,
                        "",
                        "",
                        $order->id,
                        $order->name,
                        $order->id,
                    );
                }
            }
            

            $response['success'] = true;
            $response['returns'] = $ordersArray;
        }
        else {
            $response['success'] = false;
            $response['message'] = 'There are no orders in this manifest that use Poslaju.';
        }

        return response()->json($response);
    }

    // print all documents for each order
    public function printDocuments($id) {
        // get all non-cancelled orders in this manifest
        $orders = PickingItem::select('orders.id', 'channels.docs_to_print', 'channels.channel_type_id')
                    ->leftjoin('order_items', 'order_items.id', '=', 'picking_items.item_id')
                    ->leftjoin('orders', 'orders.id', '=', 'order_items.order_id')
                    ->leftjoin('channels', 'channels.id', '=', 'orders.channel_id')
                    ->where('manifest_id', '=', $id)
                    ->where('orders.cancelled_status', '=', 0)
                    ->where('picking_items.item_type','=','OrderItem')
                    ->groupBy('orders.id')
                    ->get();
        
        /*
         * Requirements:
         * Zalora, order sheet, hw tax invoice, zalora tax invoice
         * [shopify] polo haus -  order sheet, hw tax invoice, return slip
         * [shopify] Fabspy - order sheet, hw tax invoice, return slip
         * All other channels - order sheet, hw tax invoice
         */

        // make a pdf to contain all documents to be printed
        $data = array();
        $pageBreak = '<div class="page-break"></div>';

        foreach ($orders as $order) {
            $docsToPrint = explode(', ', $order->docs_to_print);

            if (in_array(config('globals.docs_to_print.ORDER_SHEET'), $docsToPrint)) 
                $data['documents'][] = ((string) view('orders.order_sheet_page', $this->orderRepo->getOrderSheetInfo($order->id))) . $pageBreak;
            
            if (in_array(config('globals.docs_to_print.HW_TAX_INVOICE'), $docsToPrint)) {
                $data['documents'][] = $this->generateTaxInvoicePage($order->id) . $pageBreak;
                $data['documents'][] = $this->generateTaxInvoicePage($order->id) . $pageBreak;
            }
            
            if ((in_array(config('globals.docs_to_print.RETURN_SLIP'), $docsToPrint))) {
                $data['documents'][] = ((string) view('orders.return_slip_page', $this->orderRepo->getReturnSlipInfo($order->id))) . $pageBreak;
            }
            
            if ($order->channel_type_id==9 && (in_array(config('globals.docs_to_print.ZALORA_TAX_INVOICE'), $docsToPrint))) {
                // generate zalora invoice
                $invoices = $this->getInvoices($order->id);
                if (!(is_null($invoices) || empty($invoices))) {
                    foreach ($invoices as $invoice)
                        $data['documents'][] = $invoice->document_content . $pageBreak;
                }
            }
        }

        return response()->json($data);
    }

    public function assignUser(Request $request, $id)
    {
        $userId = $request->get('user_id');

        $data = [
            'user_id' => $userId,
        ];

        $rules = [
            'user_id' => 'required|integer|exists:users,id',
        ];

        $v = \Validator::make($data, $rules, array());

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        Activity::log('Picking manifest ('.$id.') was assigned to user ID ['.$userId.'].', $this->authorizer->getResourceOwnerId());

        return response()->json($this->manifestRepo->assignUser($data, $id));
    }
}