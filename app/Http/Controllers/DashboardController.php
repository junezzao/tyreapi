<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Repositories\DashboardStatisticsRepository;
use App\Helpers\Helper;
use App\Models\User;

class DashboardController extends Controller
{
    public function __construct() {
        $this->middleware('auth:web');
    }   

    public function index() {
        return view('dashboard');
    }
}
