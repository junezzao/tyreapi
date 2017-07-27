<?php namespace App\Http\Controllers\Mobile;

use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;
use App\Helpers\Helper;
use App\Models\User;
use App\Models\Admin\Merchant;
use App\Http\Controllers\Controller;


class MainController extends Controller
{
	public function __construct()
	{
		$this->user = User::with('merchant')->find(Authorizer::getResourceOwnerId());

	}

	public function merchant()
	{
		if (empty($this->user->merchant_id)) {
			$merchantId = json_decode(Merchant::all()->pluck('id'), true);
		}else {
			$merchantId = $this->user->merchant_id;
		}
		return response()->json(Merchant::with('channels')->find($merchantId));
	}
}