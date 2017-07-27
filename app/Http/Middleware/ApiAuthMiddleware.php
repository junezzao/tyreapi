<?php

namespace App\Http\Middleware;

use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use Illuminate\Support\Facades\Route;
use Request;
use Redis;
use Closure;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (env('APP_ENV')=='production') {
            $partner_id = Request::header('partnerid'); // get the partner id
            $error = false;
            $access_token = Request::header('accesstoken');
            $time = rawurldecode(Request::header('timestamp'));
            // \Log::info($access_token.$time.$app_id);
            $oauth  = \OAuthClient::firstOrNew(['authenticatable_id'=>$partner_id, 'authenticatable_type'=>'Partner']);
            // $app_id = Request::header('app_id');
            $token = hash_hmac('sha256', $oauth->id.$oauth->slug.$oauth->secret.$time, false);
            if ($token !== $access_token) {
                $message = trans('errors.InvalidAccessToken');
                $error = true;
            }
            // elseif(){// check expiry

            // }
            if ($error) {
                $errors =  response()->json(
                 array(
                    'code' =>  401,
                    'error' => [
                        'description'=> $message
                    ]
                ));
                throw new HttpResponseException($errors);
            }
        }
        $log = new \APILog;
        $log->route = Route::currentRouteName();
        $log->method = Request::method();
        $log->url = Request::url();
        // $log->datetime = date('Y-m-d H:i:s');
        $log->header = serialize(Request::header());
        $log->inputs = serialize(Request::all());
        $log->save();
        // \Log::info(print_r($log, true));
        // Redis::set('log_'.uniqid(), serialize($log));
        return $next($request);
    }
}
