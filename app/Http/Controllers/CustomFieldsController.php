<?php namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Exceptions\ValidationException as ValidationException;
use App\Repositories\CustomFieldsRepository;
use App\Http\Requests;
use Cache;

class CustomFieldsController extends Controller
{
    protected $customfields;

    public function __construct(CustomFieldsRepository $customfields)
    {
        $this->customfields = $customfields;
    }

    public function index()
    {

        // return response()->json(['channel_skus'=> $this->channelsku->skip(\Input::get('start',0))->take(\Input::get('limit',50))->all()]);
    }

    // get all custom fields for a channel
    public function getCfByChannel($id)
    {
        $customFields = $this->customfields->getCF($id);

        if (!is_null($customFields))
            return response()->json(['success'=>true,'data'=>$customFields]);
        else {
            return response()->json(['success'=>true, 'message'=>'no custom fields found', 'data'=> null]);
        }
    }

    public function updateCF(Request $request, $id)
    {
        $data = $request->all();
        $customFields = $this->customfields->updateCF($id, $data);
        return $customFields;
    }

    public function deleteCF($id) {
        $data = \Input::all();
        $customFields = $this->customfields->deleteCF($id, $data);
        return $customFields;
    }

    // custom fields data
    public function getCFData($id) {
        return $this->customfields->getCFData($id);
    }
}
