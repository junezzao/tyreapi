<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\DataRepositoryContract as DataRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Repositories\Eloquent\DataSheetRepository as DataSheetRepo;
use App\Models\DataSheet;
use App\Models\Data;
use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;

class DataRepository extends Repository implements DataRepositoryInterface
{
    protected $model;

    protected $skipCriteria = true;

    protected $dataSheetRepo;

    protected $user_id;

    public function __construct(Data $model)
    {
        $this->model = $model;
        $this->dataSheetRepo = new DataSheetRepo;
        $this->user_id = Authorizer::getResourceOwnerId();
    }
  /**
   * Specify Model class name
   *
   * @return mixed
   */
    public function model()
    {
        return 'App\Models\Data';
    }

    public function create(array $data)
    {
        if(count($data['items']) > 10) {
          // return ['success'=>false, 'exceed_limit'=>true];
        }

        // validate data
        \Log::info('Validating...');
        $errorRows = $this->validate($data);
        \Log::info('Finished validating...');
        $remarks = array();
        $invalidLines = array();
        foreach($errorRows['error'] as $key => $error) {
          $itemNo = explode('.', $key);
          $line = $itemNo[1]+1;
          $remarks[] = 'Error at line #' . $line . ': ' . $error[0];

          if(!in_array($line, $invalidLines)) array_push($invalidLines, $line);
        }
        // to eliminate identical validation error
        $remarks = array_values(array_unique($remarks));

        // delete user's data sheet, if exists
        $lastDataSheet = DataSheet::where('user_id', $this->user_id)->first();
        if(!empty($lastDataSheet)) {
          DataSheet::find($lastDataSheet->id)->data()->forceDelete();
          DataSheet::find($lastDataSheet->id)->forceDelete();
        }
        \Log::info('Deleted existing sheet...');

        // create data sheet
        $dataSheetData = array(
          'user_id' => $this->user_id,
          'filename' => $data['filename'],
          'total_count' => count($data['items']),
          'invalid_count' => count($invalidLines),
          'invalid_pct' => number_format(count($invalidLines) / count($data['items']) * 100, 2),
          'summary' => json_encode(array()),
          'remarks' => json_encode($remarks)
        );

        $dataSheet = $this->dataSheetRepo->create($dataSheetData);
        \Log::info('Created new sheet...');

        // insert data
        foreach($data['items'] as $key => $val) {
          $val['sheet_id'] = $dataSheet->id;
          $jobSheetDate = null;
          if(isset($val['jobsheet_date']) && isset($val['jobsheet_date']['date'])) {
            $jobSheetDate = date('Y-m-d', strtotime($val['jobsheet_date']['date']));
          }
          $val['jobsheet_date'] = $jobSheetDate;

          // \Log::info(print_r($val, true));
          $dataCreated = parent::create($val);
          \Log::info('Created data #'.$dataCreated->id);
        }

        $summary = $this->dataSheetRepo->getSummary($dataSheet->id);
        DataSheet::where('id', $dataSheet->id)->update(['summary' => json_encode($summary)]);

        Activity::log('Data sheet ('. $dataSheet->id .') uploaded successfully', $this->user_id);

        return ['success'=>true];
    }

    public function validate($data)
    {
      $messages = [];
      $rules = [
        'items' => 'required|array'
      ];

      foreach($data['items'] as $key => $val)
      {
        $rules['items.'.$key.'.line_number'] = 'required|numeric';
        $rules['items.'.$key.'.jobsheet_date'] = 'required';

        // if jobsheet date is given, check if it is in date format
        // this validation only applies when jobsheet date is given, therefore in case jobsheet date not given at all, validation message will not appear twice for jobsheet_date.required and jobsheet_date.date.required
        if(isset($val['jobsheet_date']) && !empty($val['jobsheet_date']))
          $rules['items.'.$key.'.jobsheet_date.date'] = 'required|date';

        $rules['items.'.$key.'.jobsheet_no'] = 'required';
        $rules['items.'.$key.'.inv_no'] = 'required';
        $rules['items.'.$key.'.inv_amt'] = 'required|numeric';
        $rules['items.'.$key.'.jobsheet_type'] = 'required|in:YARD,BREAKDOWN';
        $rules['items.'.$key.'.truck_no'] = 'required_without_all:items.'.$key.'.pm_no,items.'.$key.'.trailer_no';
        $rules['items.'.$key.'.pm_no'] = 'required_without_all:items.'.$key.'.truck_no,items.'.$key.'.trailer_no';
        $rules['items.'.$key.'.trailer_no'] = 'required_without_all:items.'.$key.'.truck_no,items.'.$key.'.pm_no';
        $rules['items.'.$key.'.customer_name'] = 'required';
        $rules['items.'.$key.'.odometer'] = 'required|numeric';
        $rules['items.'.$key.'.position'] = 'required|numeric';
        $rules['items.'.$key.'.in_attr'] = 'required|in:NT,STK,COC,USED';
        $rules['items.'.$key.'.in_price'] = 'sometimes|numeric';
        $rules['items.'.$key.'.in_size'] = 'required';
        $rules['items.'.$key.'.in_brand'] = 'required';
        $rules['items.'.$key.'.in_pattern'] = 'required';
        $rules['items.'.$key.'.in_retread_brand'] = 'required_if:items.'.$key.'.in_attr,STK,COC';
        $rules['items.'.$key.'.in_retread_pattern'] = 'required_if:items.'.$key.'.in_attr,STK,COC';
        $rules['items.'.$key.'.in_serial_no'] = 'required';
        $rules['items.'.$key.'.in_job_card_no'] = 'required_if:items.'.$key.'.in_attr,STK,COC';
        $rules['items.'.$key.'.out_reason'] = 'required';
        $rules['items.'.$key.'.out_size'] = 'required';
        $rules['items.'.$key.'.out_brand'] = 'required';
        $rules['items.'.$key.'.out_pattern'] = 'required';
        $rules['items.'.$key.'.out_serial_no'] = 'required';
        $rules['items.'.$key.'.out_rtd'] = 'required|numeric';

        $messages['items.'.$key.'.line_number.required'] = 'Ref field is required.';
        $messages['items.'.$key.'.line_number.numeric'] = 'Ref must be a number.';
        $messages['items.'.$key.'.jobsheet_date.required'] = 'Date field is required.';
        $messages['items.'.$key.'.jobsheet_date.date.required'] = 'Date is invalid.';
        $messages['items.'.$key.'.jobsheet_date.date.date'] = 'Date is invalid.';
        $messages['items.'.$key.'.jobsheet_no.required'] = 'Jobsheet No field is required.';
        $messages['items.'.$key.'.inv_no.required'] = 'Invoice No field is required.';
        $messages['items.'.$key.'.inv_amt.required'] = 'Invoice Amount field is required.';
        $messages['items.'.$key.'.inv_amt.numeric'] = 'Invoice Amount must be a number.';
        $messages['items.'.$key.'.jobsheet_type.required'] = 'Yard / Breakdown field is required.';
        $messages['items.'.$key.'.jobsheet_type.in'] = 'Yard / Breakdown is invalid. Accepted values: Yard, Breakdown.';
        $messages['items.'.$key.'.truck_no.required_without_all'] = 'One of Truck / PM / Trailer Number must exist.';
        $messages['items.'.$key.'.pm_no.required_without_all'] = 'One of Truck / PM / Trailer Number must exist.';
        $messages['items.'.$key.'.trailer_no.required_without_all'] = 'One of Truck / PM / Trailer Number must exist.';
        $messages['items.'.$key.'.customer_name.required'] = 'Customer Name field is required.';
        $messages['items.'.$key.'.odometer.required'] = 'Odometer field is required';
        $messages['items.'.$key.'.odometer.numeric'] = 'Odometer must be a number';
        $messages['items.'.$key.'.position.required'] = 'Position field is required.';
        $messages['items.'.$key.'.position.numeric'] = 'Position must be a number.';
        $messages['items.'.$key.'.in_attr.required'] = 'Tyre In Attribute is required';
        $messages['items.'.$key.'.in_attr.in'] = 'Tyre In Attribute is invalid. Accepted values: NT, STK, COC, USED.';
        $messages['items.'.$key.'.in_price.numeric'] = 'Tyre In Price must be a number.';
        $messages['items.'.$key.'.in_size.required'] = 'Tyre In Size is required.';
        $messages['items.'.$key.'.in_brand.required'] = 'Tyre In Brand is required.';
        $messages['items.'.$key.'.in_pattern.required'] = 'Tyre In Pattern is required.';
        $messages['items.'.$key.'.in_retread_brand.required_if'] = 'Tyre In Retread Brand field is required when Tyre In Attribute is :value.';
        $messages['items.'.$key.'.in_retread_pattern.required_if'] = 'Tyre In Retread Pattern field is required when Tyre In Attribute is :value.';
        $messages['items.'.$key.'.in_serial_no.required'] = 'Tyre In Serial No is required.';
        $messages['items.'.$key.'.in_job_card_no.required_if'] = 'Tyre In Job Card No field is required when Tyre In Attribute is :value.';
        $messages['items.'.$key.'.out_reason.required'] = 'Tyre Out Reason is required.';
        $messages['items.'.$key.'.out_size.required'] = 'Tyre Out Size is required.';
        $messages['items.'.$key.'.out_brand.required'] = 'Tyre Out Brand is required.';
        $messages['items.'.$key.'.out_pattern.required'] = 'Tyre Out Pattern is required.';
        $messages['items.'.$key.'.out_serial_no.required'] = 'Tyre Out Serial No is required.';
        $messages['items.'.$key.'.out_rtd.required'] = 'Tyre Out RTD field is required.';
        $messages['items.'.$key.'.out_rtd.numeric'] = 'Tyre Out RTD must be a number.';
      }

      $v = \Validator::make($data, $rules, $messages);

      if ($v->fails()) {
        return ['error'=>$v->errors()->toArray()];
        // throw new ValidationException($v);
      }

      return true;
    }
}
