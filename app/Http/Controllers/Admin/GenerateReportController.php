<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;
use App\Repositories\GenerateReportRepository as GenerateReportRepo;
use App\Repositories\MerchantStatisticsRepository as MerchantStatsRepo;
use App\Exceptions\ValidationException as ValidationException;

class GenerateReportController extends AdminController
{
    
    public function __construct() { }   

    public function getDataTable(Request $request)
    {        
        $reportTypes = $request->get('reportTypes');
        $totalDate = $request->get('totalDate');
        $inputs = $request->all();
        


        if($totalDate < 180){
            if($reportTypes == 'Sales'){
            $table = new GenerateReportRepo($inputs);
            }else{
                //return error message
                $test = 1;
            }
        }else{
            //return no data
            $test = 1;
        }
        
        
        
        return $table->getDataTable();
        //return response()->json($request->all());$table->getDataTable();
    }
}