<?php namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Traits\ReportDataGeneration;
use LucaDegasperi\OAuth2Server\Authorizer;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use App\Models\User;
use Helper;
use App\Exceptions\ValidationException as ValidationException;

class ReportsController extends Controller
{
	use ReportDataGeneration;

	protected $authorizer;

    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->authorizer = $authorizer;
    }

    public function show(Request $request)
    {

    	$duration = explode(' - ', $request->input('report-date-range'));
    	$start = $duration[0];

    	$end = $duration[1];
    	$merchants = $request->input('merchant');
    	$channels = $request->input('channel');
    	$reportData = '';

        switch($request->input('report-type')) {
            case 'sales':
                $dateRange = $this->convertDate(["from_date" => $start, "to_date" => $end]);
                $reportData =  $this->getSalesReportData($dateRange, $merchants, $channels, true);
                //$reportData['saleMasterData'] = $data['saleMasterData'];
                //$repportData['totalOrders'] = $data['totalOrders'];
                //$repportData['totalOrdersValue'] = $data['totalOrdersValue'];
                //$repportData['totalOrderItems'] = $data['totalOrderItems'];
                //$repportData['totalOrderItemsValue'] = $data['totalOrderItemsValue'];
                break;
            case 'merchant':
                $dateRange = $this->convertDate(["from_date" => $start, "to_date" => $end]);
                $reportData = $this->merchantPerformanceOverview($dateRange, $merchants);
                break;
            case 'returns':
                $dateRange = $this->convertDate(["from_date" => $start, "to_date" => $end]);
                $reportData = $this->getReturnsReportData($dateRange, $merchants, $channels, true);
                break;
        }

        return response()->json($reportData);
    }

    public function merchantPerformance(Request $request, $id) {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $dateRange = $this->convertDate(["from_date" => $fromDate, "to_date" => $toDate]);
        $data['summary'] = $this->merchantPerformanceBreakdownSummary($id, $dateRange);
        $data['table'] = $this->merchantPerformanceBreakdown($id, $dateRange);
        return response()->json($data);

    }

    // where $input contains ["from_date" => date, "to_date" => date]
    public function convertDate($input) {
        $rules = [
           'from_date' => 'required|date|date_format:Y-m-d',
           'to_date' => 'required|date|date_format:Y-m-d'
        ];

        $messages = [];

        $v = \Validator::make($input, $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        // convert to utc timezone
        try {
            $from = trim($input['from_date']. " 00:00:00");
            $to = trim($input['to_date']. " 23:59:59");
            $adminTz = User::where('id', '=', $this->authorizer->getResourceOwnerId())->value('timezone');
            if($from != '0000-00-00 00:00:00'){
                $from = Helper::convertTimeToUTC($from, $adminTz);
            }
            if($to != '0000-00-00 00:00:00'){
                $to = Helper::convertTimeToUTC($to, $adminTz);
            }

            return [$from, $to];
        }
        catch(NoActiveAccessTokenException $e) {
            return [$from, $to];
        }
    }
}

