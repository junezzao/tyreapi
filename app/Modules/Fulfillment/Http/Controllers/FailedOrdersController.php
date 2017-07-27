<?php
namespace App\Modules\Fulfillment\Http\Controllers;
use LucaDegasperi\OAuth2Server\Authorizer;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Models\Admin\Order;
use App\Models\Admin\Channel;
use App\Models\Admin\ChannelDetails;
use App\Models\Admin\Merchant;
use Activity;
use Illuminate\Http\Exception\HttpResponseException as HttpResponseException;
use App\Http\Controllers\Controller;
use App\Repositories\FailedOrderRepository as FailedOrderRepo;
use App\Modules\Channels\Repositories\Eloquent\ChannelRepository as ChannelRepo;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Criteria\FailedOrder\ByChannel;


class FailedOrdersController extends Controller
{
    protected $authorizer;
    protected $userRepo;
    protected $failedOrderRepo;

    public function __construct(Authorizer $authorizer, FailedOrderRepo $failedOrderRepo, UserRepository $userRepo)
    {
        $this->middleware('oauth');
        $this->userRepo = $userRepo;
        $this->failedOrderRepo = $failedOrderRepo;
        $this->authorizer = $authorizer;
    }

    public function throwError($msg) {
        $errors = response()->json(array(
            'code' => 422,
            'error' => $msg
        ));

        throw new HttpResponseException($errors);
    }

    public function index()
    {
        if(request()->get('channel_id')){
            $this->failedOrderRepo->pushCriteria(new ByChannel(request()->get('channel_id')));
        }
        $user = $this->userRepo->find($this->authorizer->getResourceOwnerId());
        $userRole = $user->getRoles();
        if($userRole[0]->level < 3){
            if(isset($user->merchant_id)){
                // if user is client admin or client user, filter by merchant.
                $channelRepo = new ChannelRepo(new Channel, new ChannelDetails, new Merchant);
                // get list of channel IDs under the merchant
                $channelIds = $channelRepo->byMerchantWithTrashed($user->merchant_id)->channels->pluck('id');
                $failedOrder = $this->failedOrderRepo->with('channel', 'user')->whereIn('channel_id', $channelIds)->all();
            }else{
                $failedOrder = array();
            }
        }else{
            $failedOrder = $this->failedOrderRepo->with('channel', 'user')->all();
        }
        return response()->json([
            'failedOrders'=> $failedOrder,
        ]);
    }

    public function create()
    {
    }

    public function show($id)
    {
    }

    public function edit($id)
    {
    }

    public function update($id)
    {
    }

    public function destroy($id)
    {
    }

    public function discard($id)
    {
        $failedOrder = $this->failedOrderRepo->find($id);

        if($failedOrder && $failedOrder->status != 'Discarded'){
            $data = array(
                'status' => 4,
            );

            return response()->json($this->failedOrderRepo->update($data, $id, 'failed_order_id'));
        }else{
            $errors =  response()->json(
             array(
                'code' =>  422,
                'error' => 'Unable to perform discard action on record.',
            ));

            throw new HttpResponseException($errors);
        }
    }

    public function pending($id)
    {
        $failedOrder = $this->failedOrderRepo->find($id);

        if($failedOrder){
            $data = array(
                'status' => 2,
            );
            $this->failedOrderRepo->update($data, $id, 'failed_order_id');

            return response()->json($failedOrder);
        }else{
            $errors =  response()->json(
             array(
                'code' =>  422,
                'errors' => 'Unable to find record.',
            ));

            throw new HttpResponseException($errors);
        }
    }
}
