<?php
namespace App\Repositories\Eloquent;

use App\Repositories\Repository as Repository;
use App\Repositories\Contracts\IssuingCompanyRepository as IssuingCompanyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use App\Exceptions\ValidationException as ValidationException;
use App\Models\Admin\IssuingCompany;
use App\Models\Admin\Channel;
use App\Models\Admin\RunningNumber;
use Log;

class IssuingCompanyRepository extends Repository implements IssuingCompanyRepositoryInterface
{
    protected $model;

    protected $role;

    protected $skipCriteria = false;

    public function __construct()
    {
        parent::__construct();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Admin\IssuingCompany';
    }

    public function create(array $data)
    {
        // Inputs validations

        $rules = array(
            'name' => 'required|string',
            'address' => 'required',
            'gst_reg' => 'required',
            'gst_reg_no' => 'required_if:gst_reg,1',
            'prefix' => 'required|unique:issuing_companies,prefix',
            'date_format' => 'required|string',
            'logo_url' => 'sometimes|url',
        );
        $messages = [];


        $v = \Validator::make($data, $rules, $messages);

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
        $data['name'] = htmlspecialchars($data['name']);
        $data['name'] = htmlentities(($data['name']));
        $data['address'] = str_replace(PHP_EOL,"\\n",$data['address']);
        if(isset($data['extra'])&& isset($data['extra_detail']))
        {
            $tmp= array();
            foreach($data['extra'] as $key => $val){
                $tmp[$val] = $data['extra_detail'][$key];
            }
            $data['extra'] = empty($tmp)?'':json_encode($tmp);
            unset($data['extra_detail']);
        }

        $model = parent::create($data);

        // Insert running numbers
        $taxInvoiceRunningNumber = new RunningNumber;
        $creditNoteRunningNumber = new RunningNumber;

        $taxInvoiceRunningNumber->prefix = $creditNoteRunningNumber->prefix = $data['prefix'];
        $taxInvoiceRunningNumber->current_no = $creditNoteRunningNumber->current_no = '00000';

        $taxInvoiceRunningNumber->type = 'tax_invoice';
        $creditNoteRunningNumber->type = 'credit_note';

        $taxInvoiceRunningNumber->save();
        $creditNoteRunningNumber->save();

        return $this->find($model->id);
    }

    public function update(array $data, $id, $attribute='id')
    {
        // Inputs validations
        $rules = [
            'name' => 'required|string',
            'address' => 'required',
            'gst_reg' => 'required',
            'gst_reg_no' => 'required_if:gst_reg,1',
            'prefix' => 'unique:issuing_companies,prefix,'.$id,
            'date_format' => 'string',
            'logo_url' => 'sometimes|url',
        ];
        $messages = array();

        $v = \Validator::make($data, $rules, $messages);

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
        if(isset($data['name']))
        {
            $data['name'] = htmlspecialchars($data['name']);
            $data['name'] = htmlentities($data['name']);
        }
        $data['address'] = str_replace(PHP_EOL,"\\n",$data['address']);
        if(isset($data['extra'])&& isset($data['extra_detail']))
        {
            $tmp= array();
            foreach($data['extra'] as $key => $val){
                $tmp[$val] = $data['extra_detail'][$key];
            }
            $data['extra'] = empty($tmp)?'':json_encode($tmp);
            unset($data['extra_detail']);
        }
        $model = parent::update($data, $id, $attribute);
        return $this->find($id);
    }
}
