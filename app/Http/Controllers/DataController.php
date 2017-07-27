<?php
namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DataRepositoryContract as DataRepository;
use App\Repositories\Contracts\DataSheetRepositoryContract as DataSheetRepository;
use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;

class DataController extends Controller
{
	protected $authorizer;
	protected $dataRepo;
	protected $dataSheetRepo;

    public function __construct(Authorizer $authorizer, DataRepository $dataRepo, DataSheetRepository $dataSheetRepo)
    {
        $this->middleware('oauth');
        $this->dataRepo = $dataRepo;
        $this->dataSheetRepo = $dataSheetRepo;
    }

    public function getSheet($userId)
	{
		$sheet = $this->dataSheetRepo->getSheetByUser($userId);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}

	public function getData($userId)
	{
		$data = $this->dataSheetRepo->getDataByUser($userId);
		// \Log::info(print_r($sheet, true));
		return response()->json($data);
	}

	public function viewTruckPosition($userId)
	{
		$data = $this->dataSheetRepo->viewTruckPosition($userId);
		//\Log::info(print_r($data, true));
		return response()->json($data);
	}

	public function viewTruckService($userId)
	{
		$data = $this->dataSheetRepo->viewTruckService($userId);
		//\Log::info(print_r($data, true));
		return response()->json($data);
	}

	public function viewTyreBrand($userId)
	{
		$data = $this->dataSheetRepo->viewTyreBrand($userId);
		//\Log::info(print_r($data, true));
		return response()->json($data);
	}

	/*public function index()
	{
        // items causing delays
		$batches = $this->purchaseRepo->all(request()->all());
		return response()->json(['batches'=>$batches]);
	}

	public function show($id)
	{
		$batch = $this->purchaseRepo->with('items')->findOrFail($id);
		return response()->json($batch);
	}

	public function showWithTrashed($id)
	{
		$batch = $this->purchaseRepo->with('itemsWithTrashed')->findOrFail($id);
		return response()->json($batch);
	}

	// search for batch by id - used in stock transfer
	public function findBatch($batchId, $merchantId, $channelId)
	{
		$batch = $this->purchaseRepo->findBatch($batchId, $merchantId, $channelId);
		if (!empty($batch)) {
			return json_encode(array("success"=> true, "items"=> $batch['items']));
		}
		else {
			return json_encode(array("success"=> false, "message"=> "The procurement batch was not found. Please try again."));
		}
	}*/

	public function store()
	{
		//\Log::info(print_r(request()->all(), true));
		set_time_limit(300);
		\DB::beginTransaction();
		$inputs = request()->all();
		$inputs['items'] = json_decode($inputs['items'], true);
		//\Log::info('input...'.print_r(request()->all(), true));
		$data = $this->dataRepo->create($inputs);

		\DB::commit();
		return response()->json($data);
	}

	/*public function update($id)
	{
		$inputs = request()->all();
		$model = $this->purchaseRepo->update($inputs,$id);
		return response()->json($model);
	}

	public function destroy($id)
	{
		$this->itemRepo->clear($id);
       	$acknowledge = $this->purchaseRepo->delete($id);
		return response()->json(['acknowledge'=>$acknowledge]);
	}

	public function receive($id)
	{
		$batch = $this->purchaseRepo->receive($id);
		return response()->json($batch);
	}*/

}
