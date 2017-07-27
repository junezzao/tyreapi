<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;
use App\Repositories\MerchantStatisticsRepository as MerchantStatsRepo;
use App\Repositories\DashboardStatisticsRepository as DashboardStatsRepo;
use App\Exceptions\ValidationException as ValidationException;

class StatisticsController extends Controller
{
    public function __construct() {
        $this->dashboardRepo = new DashboardStatsRepo;
        $this->token_client = \OAuthClient::find(Authorizer::getClientId());
        $this->merchant_id = null;
        // Mobile Token
        if(strcasecmp($this->token_client->authenticatable_type, 'HWMobile')==0)
        {
            $this->user = User::with('merchant')->find(Authorizer::getResourceOwnerId());
            $this->merchant_id = $this->user->merchant_id;
            $this->dashboardRepo->byMerchant($this->user->merchant_id);
            // auto include merchant id in datatable columns search
            request()->merge(['columns'=>[['name'=>'merchant_id','search'=>['value'=>$this->user->merchant_id]]]]);
        }

    }   

    // stats for dashboard page
    public function index()
    {
        $data = array();
        
        // retrieve weekly stats
        $query = "SELECT * FROM dashboard_stats where frequency = 'Weekly' and created_at = (select max(created_at) from dashboard_stats where frequency = 'Weekly')";
        $stats = \DB::select(\DB::raw($query));
        if (!empty($stats)) {
            $data['last_updated'] = date('Y-m-d H:i:s', strtotime($stats[0]->created_at));

            // convert to user timezone
            try{
                $userId = Authorizer::getResourceOwnerId();
                $adminTz = User::where('id', '=', $userId)->value('timezone');
                if($data['last_updated'] != '0000-00-00 00:00:00'){
                    $data['last_updated'] = Helper::convertTimeToUserTimezone($data['last_updated'], $adminTz);
                }
            }catch(NoActiveAccessTokenException $e){
                return $data['last_updated'];
            }

            foreach ($stats as $stat) {
                $data['weekly'][$stat->title] = json_decode($stat->data);
            }
        }

        // retrieve monthly stats
        $query = "SELECT * FROM dashboard_stats where frequency = 'Monthly' and created_at = (select max(created_at) from dashboard_stats where frequency = 'Monthly')";
        $stats = \DB::select(\DB::raw($query));
        if (!empty($stats)) {
            foreach ($stats as $stat) {
                $data['monthly'][$stat->title] = json_decode($stat->data);
            }
        }

        // retrieve tri-monthly stats
        $query = "SELECT * FROM dashboard_stats where frequency = 'Trimonthly' and created_at = (select max(created_at) from dashboard_stats where frequency = 'Trimonthly')";
        $stats = \DB::select(\DB::raw($query));
        if (!empty($stats)) {
            foreach ($stats as $stat) {
                $data['trimonthly'][$stat->title] = json_decode($stat->data);
            }
        }

        return $data;
    }

    // generate merchant stats for mobile app
    public function merchantStats(Request $request)
    {
        /* 
         * performance on each channel (in rank of best performing channels),
         * the total number of items sold
         * total items sold per channel,
         * total value of items sold,
         * total value sold per channel,
         */
        $dateRange = $this->validateAndConvertDateRange($request);
        $repo = new MerchantStatsRepo($this->merchant_id);
        return $repo->getStats($dateRange);
    }

    /*public function merchantPerformance(Request $request, $id)
    {
        $dateRange = $this->validateAndConvertDateRange($request);
        $repo = new MerchantStatsRepo($id);
        return $repo->merchantPerformanceBreakdown($dateRange);
    }*/

    public function validateAndConvertDateRange(Request $request)
    {
        $rules = [
           'from_date' => 'required|date|date_format:Y-m-d',
           'to_date' => 'required|date|date_format:Y-m-d'
        ];

        $messages = [];

        $v = \Validator::make($request->all(), $rules, $messages);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        // convert to utc timezone
        try {
            $from = trim($request->get('from_date'). " 00:00:00");
            $to = trim($request->get('to_date'). " 00:00:00");
            $adminTz = User::where('id', '=', Authorizer::getResourceOwnerId())->value('timezone');
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

    // stats for dashboard counters
    public function counters(Request $request) {

        $result = array();
        $result = array_merge($result, $this->dashboardRepo->countOrdersAndOrderItems());
        $result = array_merge($result, $this->dashboardRepo->countCancelledItems());
        $result = array_merge($result, $this->dashboardRepo->countReturnedItems());

        if(strcasecmp($this->token_client->authenticatable_type, 'HWMobile')==0)
        {
            $result = array_merge($result, $this->dashboardRepo->countReturnedTransitItems());
            $result = array_merge($result, $this->dashboardRepo->countOutOfStockSKUs());
        }

        return response()->json($result);
    }
}
