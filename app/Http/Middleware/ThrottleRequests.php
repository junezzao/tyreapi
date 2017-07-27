<?php
namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottle;

class ThrottleRequests extends BaseThrottle {

	protected function buildResponse($key, $maxAttempts)
    {
        $response = response()->json([
            'code' => config('globals.status_code.TOO_MANY_REQUEST',429),
            'error' => ['too_many_request'=>['Too Many Attempts. Please refer to Retry-After header.']]
            ], 429);

        $retryAfter = $this->limiter->availableIn($key);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );
    }
}

?>