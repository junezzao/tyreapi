<?php
namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DataRepositoryContract as DataRepository;
use App\Repositories\Contracts\DataSheetRepositoryContract as DataSheetRepository;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Request;
use Activity;

class ReportController extends Controller
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

    public function serialNoAnalysis($userId, $type)
	{
		$sheet = $this->dataSheetRepo->serialNoAnalysis($userId, $type);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}

	public function odometerAnalysis(Request $request, $userId)
	{
		$sheet = $this->dataSheetRepo->odometerAnalysis($userId, $request->check_trailer);
		// \Log::info(print_r($request->all(), true));
		return response()->json($sheet);
	}

	public function tyreRemovalMileage($userId)
	{
		$sheet = $this->dataSheetRepo->tyreRemovalMileage($userId);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}

	public function tyreRemovalRecord($userId)
	{
		$sheet = $this->dataSheetRepo->tyreRemovalRecord($userId);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}

	public function truckTyreCost(Request $request, $userId)
	{
		$sheet = $this->dataSheetRepo->truckTyreCost($userId, $request->sort, $request->limit);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}

	public function truckServiceRecord($userId)
	{
		$sheet = $this->dataSheetRepo->truckServiceRecord($userId);
		// \Log::info(print_r($sheet, true));
		return response()->json($sheet);
	}
}
