<?php

namespace App\Http\Requests\Partner;

use App\Http\Requests\Request;
use Illuminate\Http\JsonResponse;
use Response;

class StockTransferRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $partner_id =  Request::header('partnerid');
        $rules = [
            'source_id' => 'required|integer|exists:distribution_center,distribution_center_id,partner_id,'.$partner_id,
            'recipient_id' => 'required|integer|exists:distribution_center,distribution_center_id,partner_id,'.$partner_id,
            'sku_list'  => 'required|array'
        ];
        if (!empty($this->input('sku_list')) && is_array($this->input('sku_list'))) {
            foreach ($this->input('sku_list') as $key => $val) {
                $rules['sku_list.'.$key.'.sku_id'] = 'required|integer|min:1|exists:sku,sku_id';
                $rules['sku_list.'.$key.'.quantity'] = 'required|integer|min:1';
            }
        }

        return $rules;
    }

    public function response(array $errors)
    {
        $response = [
            'code'=>422,
            'error'=>$errors,
        ];
        return new JsonResponse($response, 200);
    }
}
