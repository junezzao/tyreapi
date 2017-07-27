<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Auth;

class Authenticate
{
    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::guard('web')->guest()) {
            if ($request->ajax()) {
                return response('Unauthorized.', 401);
            } else {
                return redirect()->guest(route('hw.login'));
            }
        }
        else {
            $userStatus = Auth::guard('web')->user()['status'];
            if($userStatus == 'Unverified'){
                $allowed = ['hw.users.verify', 'hw.users.show_verify', 'hw.logout'];
                
                if (!in_array($request->route()->getName(), $allowed)) {
                    flash()->error('Please verify your account first before proceeding.');
                    return redirect()->route('hw.users.show_verify');
                }
            }
        }

        return $next($request);
    }
}
