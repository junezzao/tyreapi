<?php namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use App\Repositories\Repository;
use Illuminate\Log\Writer\Log;
use Illuminate\Database\DatabaseManager\DB;
use App\Exceptions\ValidationException as ValidationException;

class MemberRepository extends Repository
{
    protected $maps =[
            'email'=>'member_email',
            'password' => 'member_password',
            'name' => 'member_name',
            'gender' => 'member_gender',
            'type' => 'member_type',
            'birthday' => 'member_birthday',
            'phone' => 'member_mobile'
        ];
    public function model()
    {
        return 'App\Models\Admin\Member';
    }
   
    public function create(array $inputs)
    {
        
        // Inputs validations
        $v = \Validator::make($inputs, [
            'email' => 'sometimes|required|email',
            'password' => 'sometimes|required|alpha_num',
            'name' => 'required|string',
            'gender' => 'sometimes|required|in:M,F',
            'type' => 'sometimes|required|integer',
            'client_id' => 'required|integer|min:1',
            'channel_id' => 'required|integer|min:1',
            'birthday' => 'sometimes|required|date_format:Y-m-d',
            'phone' => 'sometimes|required',
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }

        $newinputs = array();
        foreach ($inputs as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $inputs = $newinputs;

        $member = parent::create($inputs);
        return $this->find($member->member_id);
    }

    
    public function update(array $data, $id, $attribute="member_id")
    {
        // Inputs validations
        unset($data['channel_id']);
        unset($data['client_id']);

        $v = \Validator::make($data, [
            'email' => 'sometimes|required|email',
            'password' => 'sometimes|required|alpha_num',
            'name' => 'required|string',
            'gender' => 'sometimes|required|in:M,F',
            'type' => 'sometimes|required|integer',
            'birthday' => 'sometimes|required|date_format:Y-m-d',
            'phone' => 'sometimes|required',
        ]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
        $newinputs = array();
        foreach ($data as $k => $v) {
            $key = $k;
            if (isset($this->maps[$key])) {
                $key = $this->maps[$key];
            }
            $newinputs[$key] = $v;
        }
        $data = $newinputs;
        $member = parent::update($data, $id, $attribute);
        return $this->find($id);
    }
}
