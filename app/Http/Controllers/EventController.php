<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Jobs\SendWebhook;
use Artisan;

class EventController extends Controller
{
    /**
     * [Listen to an events for make POST request of related webhooks registered]
     * @param   $event                [Event name = orders/created, orders/updated, transfers/approved, replenishment/approved]
     * @param   $id [Model id]
     * @return  [type]
     *
     * @author Mahadhir Mohd Asnawi <mahadhir@hubwire.com>
     * @version 1.0
     */
    public function listen(Request $request
    ) {
        if($request->get('event')=='categories/updated')
        {
            Artisan::call('category:pullFromS3');
        }   
        else 
        {
            $rules = [
                'id' => 'integer|required|min:1',
                'event' => 'required|in:'.implode(',', config('api.webhook_events')),
            ];
            $v = \Validator::make($request->all(), $rules);
            if ($v->fails()) {
                throw new ValidationException($v);
            }

            dispatch(new SendWebhook($request->all()));
        }

    }
}
