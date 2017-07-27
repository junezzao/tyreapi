<?php

namespace App\Http\Middleware;

use Closure;

class OAuthAuthenticate
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
        if ($request->headers->has('Authorization')) {
            $this->authorizer->setRequest($request);
            if ($this->authorizer->validateAccessToken(false)) {
                $request->setUserResolver(function () {
                    return $this->user->find($this->authorizer->getResourceOwnerId());
                });
                return $next($request);
            } else {
                abort(401, trans('auth.access.credentials'));
            }
        }
        abort(401, trans('auth.access.missing'));
    }
}
