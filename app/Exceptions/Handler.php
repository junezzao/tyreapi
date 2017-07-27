<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\ModelException;
use Illuminate\Database\QueryException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use \League\OAuth2\Server\Exception\OAuthException as OAuthException;
use App\Exceptions\ValidationException as ValidationException;
use \LucaDegasperi\OAuth2Server\Exceptions\NoActiveAccessTokenException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        HttpException::class,
        ValidationException::class,
        OAuthException::class,
        // ModelNotFoundException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        return parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $model = explode("\\", $e->getModel());
            $response = [
                'code'=> config('globals.status_code.DATA_ERROR'),
                'error'=> [ 
                    'record_not_found' => ['No record found in '.$model[count($model)-1].' database.']
                    ]
            ];
            return response()->json($response, 200);
        }

        if ($e instanceof NotFoundHttpException) {
            $response = [
                'code'=> 404,
                'error'=> [
                    'page_not_found' => [trans('errors.NotFoundHttpException')]
                    ]
            ];
            return response()->json($response, 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            $response = [
                'code'=> 405,
                'error'=> [
                    'method_not_allowed' => [trans('errors.MethodNotAllowedHttpException')]
                    ]
            ];
            return response()->json($response, 405);
        }

        if($e instanceof OAuthException)
        {
            $response = [
                    'code'=> config('globals.status_code.OAUTH_ERROR'),
                    'error'=> [
                            $e->errorType => [ $e->getMessage() ]
                        ]
                    ];
            // return response()->json($response, $e->httpStatusCode)
            return response()->json($response, config('globals.status_code.OAUTH_ERROR'))
                    ->withHeaders($e->getHttpHeaders());
        }

        if($e instanceof NoActiveAccessTokenException)
        {
            $response = [
                    'code'=> config('globals.status_code.OAUTH_ERROR'),
                    'error'=> [
                            'access_token' => [ $e->getMessage() ]
                        ]
                    ];
            // return response()->json($response, $e->httpStatusCode)
            return response()->json($response, config('globals.status_code.OAUTH_ERROR'));
        }

        if($e instanceof ValidationException)
        {
            return response()->json([
                'code' =>  config('globals.status_code.VALIDATION_ERROR'),
                'error' => $e->errors()
            ]);
        }

        
        /*
        if($e instanceof QueryException)
        {
            $response = [
                'code'=> 500,
                'error'=> $e->getMessage()
            ];
            return response()->json($response, 500);
        }
        
        if($e instanceof RuntimeException)
        {
            // \Log::info($e);
            $response = [
                'code'=> 500,
                'error'=> $e->getMessage()//trans('errors.RuntimeException')
            ];
            return response()->json($response, 500);
        }
        */
        return parent::render($request, $e);
    }
}
