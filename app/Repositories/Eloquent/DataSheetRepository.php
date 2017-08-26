<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\DataSheetRepositoryContract as DataSheetRepositoryInterface;
use App\Repositories\Repository as Repository;
use App\Models\DataSheet;
use App\Models\Data;
use App\Exceptions\ValidationException as ValidationException;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;
use Activity;
use App\Helpers\Helper;

class DataSheetRepository extends Repository implements DataSheetRepositoryInterface
{
    protected $model;

    protected $skipCriteria = true;

    protected $user_id;

    public function __construct()
    {
        parent::__construct();
        $this->model = new DataSheet;
        $this->user_id = Authorizer::getResourceOwnerId();
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'App\Models\DataSheet';
    }

    public function distinctCustomerName($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.customer_name')->groupBy('data.customer_name')->get();
        return $rows;
    }

    public function distinctJobSheetNo($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.jobsheet_no')->groupBy('data.jobsheet_no')->get();
        return $rows;
    }

    public function distinctTruckNo($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.truck_no')->groupBy('data.truck_no')->get();
        return $rows;
    }

    public function distinctPmNo($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.pm_no')->groupBy('data.pm_no')->get();
        return $rows;
    }

    public function distinctTrailerNo($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.trailer_no')->groupBy('data.trailer_no')->get();
        return $rows;
    }

    public function distinctAttrNt($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.in_attr')->where('data.in_attr', 'NT')->get();
        return $rows;
    }

    public function distinctAttrStk($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.in_attr')->where('data.in_attr', 'STK')->get();
        return $rows;
    }

    public function distinctAttrCoc($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.in_attr')->where('data.in_attr', 'COC')->get();
        return $rows;
    }

    public function distinctAttrUsed($sheetId)
    {
        $rows = $this->model::find($sheetId)->data()->select('data.in_attr')->where('data.in_attr', 'USED')->get();
        return $rows;
    }

    public function getSummary($sheetId)
    {  
        $summary = array(
            'customer' => count($this->distinctCustomerName($sheetId)),
            'jobsheet' => count($this->distinctJobSheetNo($sheetId)),
            'truck' => count($this->distinctTruckNo($sheetId)),
            'pm' => count($this->distinctPmNo($sheetId)),
            'trailer' => count($this->distinctTrailerNo($sheetId)),
            'nt' => count($this->distinctAttrNt($sheetId)),
            'stk' => count($this->distinctAttrStk($sheetId)),
            'coc' => count($this->distinctAttrCoc($sheetId)),
            'used' => count($this->distinctAttrUsed($sheetId)),
        );

        return $summary;
    }

    public function getSheetByUser($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();
        if(!empty($sheet)) $sheet['health'] = $sheet->health();
        return $sheet;
    }

    public function getDataByUser($filter, $userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $data = array();
        if(empty($sheet)) return $data;

        $data = Data::where('sheet_id', $sheet->id);

        foreach($filter['columns'] as $index=>$column) {
            if($column['searchable'] == true) {
                if(!empty($column['search']['value'])) {
                    $data = $data->where(strtolower($column['data']), 'like', '%'.strtolower($column['search']['value']).'%');
                }
            }
        }
        
        $total = $data->count();

        $data = $data->skip($filter['start'])
                    ->take($filter['length']);

        foreach($filter['order'] as $index=>$order) {
            $data = $data->orderBy($filter['columns'][$order['column']]['data'], $order['dir']);
        }

        return ['total'=>$total, 'data'=>$data->get()->toArray()];
    }

    public function viewTruckPosition($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();
        if(!empty($sheet)) {
            $jobsheets = \DB::select('
                            select * from data
                            where sheet_id = '.$sheet->id.'
                            order by jobsheet_date asc
                        ');
            foreach($jobsheets as $jobsheet) {
                if(!empty($jobsheet->pm_no)) {
                    $return[Helper::formatEmpty($jobsheet->customer_name)]['PM'][$jobsheet->pm_no][Helper::formatEmpty($jobsheet->position)][] = array(
                        'date'      => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }
                if(!empty($jobsheet->trailer_no)) {
                    $return[Helper::formatEmpty($jobsheet->customer_name)]['Trailer'][$jobsheet->trailer_no][Helper::formatEmpty($jobsheet->position)][] = array(
                        'date'      => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }
                if(!empty($jobsheet->truck_no)) {
                    $return[Helper::formatEmpty($jobsheet->customer_name)]['Truck'][$jobsheet->truck_no][Helper::formatEmpty($jobsheet->position)][] = array(
                        'date'      => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }
                if(empty($jobsheet->pm_no) && empty($jobsheet->trailer_no) && empty($jobsheet->truck_no)) {
                    $return[Helper::formatEmpty($jobsheet->customer_name)]['(empty)']['(empty)'][Helper::formatEmpty($jobsheet->position)][] = array(
                        'date'      => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }
            }
        }

        // \Log::info('return... '.print_r($return, true));
        return array_sort_recursive($return);
    }

    public function viewTruckService($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();
        if(!empty($sheet)) {
            $jobsheets = \DB::select('
                            select * from data
                            where sheet_id = '.$sheet->id.'
                            order by jobsheet_date asc
                        ');
            foreach($jobsheets as $jobsheet) {
                $customerName   = Helper::formatEmpty($jobsheet->customer_name);
                $jobsheetNo     = Helper::formatEmpty($jobsheet->jobsheet_no);
                $invoiceNo      = Helper::formatEmpty($jobsheet->inv_no);
                $invoiceAmt     = 'RM'.number_format($jobsheet->inv_amt, 2);

                if(!empty($jobsheet->pm_no)) {
                    if(!isset($return[$customerName]['PM'][$jobsheet->pm_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt])) {
                        $return[$customerName]['PM'][$jobsheet->pm_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] = $jobsheet->in_price;
                    } else {
                        $return[$customerName]['PM'][$jobsheet->pm_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] += $jobsheet->in_price;
                    }


                   $return[$customerName]['PM'][$jobsheet->pm_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['positions'][] = array(
                        'position'  => Helper::formatEmpty($jobsheet->position),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }

                if(!empty($jobsheet->trailer_no)) {
                    if(!isset($return[$customerName]['Trailer'][$jobsheet->trailer_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt])) {
                        $return[$customerName]['Trailer'][$jobsheet->trailer_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] = $jobsheet->in_price;
                    } else {
                        $return[$customerName]['Trailer'][$jobsheet->trailer_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] += $jobsheet->in_price;
                    }


                   $return[$customerName]['Trailer'][$jobsheet->trailer_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['positions'][] = array(
                        'position'  => Helper::formatEmpty($jobsheet->position),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }

                if(!empty($jobsheet->truck_no)) {
                    if(!isset($return[$customerName]['Truck'][$jobsheet->truck_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt])) {
                        $return[$customerName]['Truck'][$jobsheet->truck_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] = $jobsheet->in_price;
                    } else {
                        $return[$customerName]['Truck'][$jobsheet->truck_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] += $jobsheet->in_price;
                    }


                   $return[$customerName]['Truck'][$jobsheet->truck_no][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['positions'][] = array(
                        'position'  => Helper::formatEmpty($jobsheet->position),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }

                if(empty($jobsheet->pm_no) && empty($jobsheet->trailer_no) && empty($jobsheet->truck_no)) {
                    if(!isset($return[$customerName]['(empty)']['(empty)'][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt])) {
                        $return[$customerName]['(empty)']['(empty)'][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] = $jobsheet->in_price;
                    } else {
                        $return[$customerName]['(empty)']['(empty)'][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['totalPrice'] += $jobsheet->in_price;
                    }


                   $return[$customerName]['(empty)']['(empty)'][Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date))][$jobsheetNo.': TOTAL_PRICE / '.$invoiceNo.', '.$invoiceAmt]['positions'][] = array(
                        'position'  => Helper::formatEmpty($jobsheet->position),
                        'in'        => $this->getTyreInfo($jobsheet, 'in', false, true, true, true),
                        'invoice'   => 'RM'.number_format($jobsheet->in_price, 2),
                        'out'       => $this->getTyreInfo($jobsheet, 'out', false, true, true, true)
                    );
                }
            }
        }

        // \Log::info('return... '.print_r($return, true));
        return array_sort_recursive($return);
    }

    public function viewTyreBrand($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array(
            'NT'            => array(),
            'NT_SUB_CON'    => array(),
            'STK'           => array(),
            'STK_SUB_CON'   => array(),
            'COC'           => array(),
            'USED'          => array(),
            'OTHER'         => array(),
        );

        if(!empty($sheet)) {
            $return['NT']           = $this->getTyreBrandNewData($sheet->id, 'NT');
            $return['NT_SUB_CON']   = $this->getTyreBrandNewData($sheet->id, 'NT_SUB_CON');
            $return['STK']          = $this->getTyreBrandRetreadData($sheet->id, 'STK');
            $return['STK_SUB_CON']  = $this->getTyreBrandRetreadData($sheet->id, 'STK_SUB_CON');
            $return['COC']          = $this->getTyreBrandRetreadData($sheet->id, 'COC');
            $return['USED']         = $this->getTyreBrandNewData($sheet->id, 'USED');
            $return['OTHER']        = $this->getTyreBrandNewData($sheet->id, 'OTHER');
        }

        //\Log::info('return... '.print_r($return, true));
        return $return;
    }

    public function getTyreBrandNewData($sheetId, $tyreAttribute) {
        $return = array();

        $jobsheets = \DB::select('
                        select * from data
                        where sheet_id = '. $sheetId .'
                        and in_attr = "'. $tyreAttribute .'"
                        order by jobsheet_date asc
                    ');
        foreach($jobsheets as $jobsheet) {
            $brand          = Helper::formatEmpty($jobsheet->in_brand);
            $pattern        = Helper::formatEmpty($jobsheet->in_pattern);
            $size           = Helper::formatEmpty($jobsheet->in_size);
            $serialNo       = Helper::formatEmpty($jobsheet->in_serial_no);
            $customerName   = Helper::formatEmpty($jobsheet->customer_name);
            $position       = Helper::formatEmpty($jobsheet->position);
            $jobsheetDate   = Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date));

            if(!empty($jobsheet->pm_no))
                $return[$brand][$pattern][$size][$serialNo][$customerName]['PM'][$jobsheet->pm_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(!empty($jobsheet->trailer_no))
                $return[$brand][$pattern][$size][$serialNo][$customerName]['Trailer'][$jobsheet->trailer_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(!empty($jobsheet->truck_no))
                $return[$brand][$pattern][$size][$serialNo][$customerName]['Truck'][$jobsheet->truck_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(empty($jobsheet->pm_no) && empty($jobsheet->trailer_no) && empty($jobsheet->truck_no))
                $return[$brand][$pattern][$size][$serialNo][$customerName]['(empty)']['(empty)'][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
        }

        // \Log::info('return... '.print_r($return, true));
        return array_sort_recursive($return);
    }

    public function getTyreBrandRetreadData($sheetId, $tyreAttribute) {
        $return = array();

        $jobsheets = \DB::select('
                        select * from data
                        where sheet_id = '. $sheetId .'
                        and in_attr = "'. $tyreAttribute .'"
                        order by jobsheet_date asc
                    ');
        foreach($jobsheets as $jobsheet) {
            $retreadBrand       = Helper::formatEmpty($jobsheet->in_retread_brand);
            $retreadPattern     = Helper::formatEmpty($jobsheet->in_retread_pattern);
            $size               = Helper::formatEmpty($jobsheet->in_size);
            $jobCardNo          = Helper::formatEmpty($jobsheet->in_job_card_no);
            $serialNo           = Helper::formatEmpty($jobsheet->in_serial_no);
            $brand              = Helper::formatEmpty($jobsheet->in_brand);
            $pattern            = Helper::formatEmpty($jobsheet->in_pattern);
            $customerName       = Helper::formatEmpty($jobsheet->customer_name);
            $position           = Helper::formatEmpty($jobsheet->position);
            $jobsheetDate       = Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date));

            if(!empty($jobsheet->pm_no))
                $return[$retreadBrand][$retreadPattern][$size][$jobCardNo.' / '.$serialNo.' [ '.$brand.' '.$pattern.' ]'][$customerName]['PM'][$jobsheet->pm_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(!empty($jobsheet->trailer_no))
                $return[$retreadBrand][$retreadPattern][$size][$jobCardNo.' / '.$serialNo.' [ '.$brand.' '.$pattern.' ]'][$customerName]['PM'][$jobsheet->trailer_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(!empty($jobsheet->truck_no))
                $return[$retreadBrand][$retreadPattern][$size][$jobCardNo.' / '.$serialNo.' [ '.$brand.' '.$pattern.' ]'][$customerName]['PM'][$jobsheet->truck_no][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
            
            if(empty($jobsheet->pm_no) && empty($jobsheet->trailer_no) && empty($jobsheet->truck_no))
                $return[$retreadBrand][$retreadPattern][$size][$jobCardNo.' / '.$serialNo.' [ '.$brand.' '.$pattern.' ]'][$customerName]['(empty)']['(empty)'][$position][] = $jobsheetDate.' ( RM'.number_format($jobsheet->in_price, 2).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' / '.Helper::formatEmpty($jobsheet->inv_no).' )';
        }

        // \Log::info('return... '.print_r($return, true));
        return array_sort_recursive($return);
    }

    public function serialNoAnalysis($userId, $type) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();
        $return['missing'] = array();
        $return['repeated'] = array();

        if(!empty($sheet)) {

            if($type == 'missing') {
                // part 1
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and in_serial_no is null or in_serial_no = ""
                                or out_serial_no is null or out_serial_no = ""
                            ');
                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);
                    
                    if(empty($jobsheet->in_serial_no)) {
                        $tyre = $this->getTyreInfo($jobsheet, 'in', false, true);

                        $return['missing'][] = [
                            'line'      => $jobsheet->line_number,
                            'jobsheet'  => $jobsheet->jobsheet_no,
                            'type'      => $jobsheet->jobsheet_type,
                            'customer'  => $jobsheet->customer_name,
                            'vehicle'   => $vehicle,
                            'position'  => $jobsheet->position,
                            'in_out'    => 'In',
                            'tyre'      => $tyre,
                            'remark'    => 'No Serial No.'
                        ];
                    }

                    if(empty($jobsheet->out_serial_no)) {
                        $tyre = $this->getTyreInfo($jobsheet, 'out', false, true);

                        $return['missing'][] = [
                            'line'      => $jobsheet->line_number,
                            'jobsheet'  => $jobsheet->jobsheet_no,
                            'type'      => $jobsheet->jobsheet_type,
                            'customer'  => $jobsheet->customer_name,
                            'vehicle'   => $vehicle,
                            'position'  => $jobsheet->position,
                            'in_out'    => 'Out',
                            'tyre'      => $tyre,
                            'remark'    => 'No Serial No.'
                        ];
                    }
                }
                // part 1 end

                return $return['missing'];
            }

            elseif($type == 'repeated') {
                // part 2
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and jobsheet_date is not null and
                                (
                                    (in_serial_no is not null and in_serial_no != "")
                                    or 
                                    (out_serial_no is not null and out_serial_no != "")
                                )
                                order by jobsheet_date asc
                            ');
                $serialNos = array();
                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    if(!empty($jobsheet->in_serial_no)) {
                        $serialNos[$jobsheet->in_serial_no][] = array(
                            'type' => 'in',
                            'info' => 'Fitting date '.Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' '.Helper::formatEmpty($vehicle).' Pos '.Helper::formatEmpty($jobsheet->position)
                        );
                    }

                    if(!empty($jobsheet->out_serial_no) && isset($serialNos[$jobsheet->out_serial_no])) { // to make sure the first record of each serial no is always IN record
                        $serialNos[$jobsheet->out_serial_no][] = array(
                            'type' => 'out'
                        );
                    }
                }

                foreach($serialNos as $serialNo => $fittings) {
                    if(count($fittings) <= 1) continue;

                    foreach($fittings as $index => $fitting) {
                        if($index > 0) {
                            if($fitting['type'] == $lastFittingType && $fitting['type'] == 'in') {
                                $return['repeated'][$serialNo][$index-1]    = $fittings[$index-1]['info']; 
                                $return['repeated'][$serialNo][$index]      = $fittings[$index]['info']; 
                            }
                        }

                        $lastFittingType = $fitting['type'];
                    }

                    if(isset($return['repeated'][$serialNo])) {
                        $return['repeated'][$serialNo] = array_values($return['repeated'][$serialNo]);
                    }
                }
                // part 2 end

                return $return['repeated'];
            }
        }

        //\Log::info('return... '.print_r($return, true));
        return [];
    }

    public function odometerAnalysis($userId, $checkTrailer, $type) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();
        $return['missing']  = array();
        $return['less']     = array();

        if(!empty($sheet)) {

            if($type == 'missing') {
                // part 1
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and (odometer is null or odometer = "")'.
                                ($checkTrailer == 'N' ? ' and (trailer_no is null or trailer_no = "")' : '')
                            );
                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    $return['missing'][] = [
                        'line'      => $jobsheet->line_number,
                        'date'      => $jobsheet->jobsheet_date,
                        'jobsheet'  => $jobsheet->jobsheet_no,
                        'vehicle'   => $vehicle,
                        'remark'    => 'No Odometer'
                    ];
                }
                // part 1 end

                return $return['missing'];
            }
            elseif($type == 'less') {
                // part 2
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and jobsheet_date is not null 
                                and odometer is not null and odometer != ""
                                order by jobsheet_date asc
                            ');
                $vehicles = array();
                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    if(!empty($vehicle)) {
                        $vehicles[$vehicle][] = array(
                            'odometer'  => $jobsheet->odometer,
                            'info'      => 'Date '.Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' Reading: '.Helper::formatEmpty($jobsheet->odometer)
                        );
                    }
                }

                foreach($vehicles as $vehicle => $readings) {
                    if(count($readings) <= 1) continue;

                    foreach($readings as $index => $reading) {
                        if($index > 0) {
                            if($reading['odometer'] < $lastReading) {
                                $return['less'][$vehicle][$index-1] = $readings[$index-1]['info']; 
                                $return['less'][$vehicle][$index]   = $readings[$index]['info']; 
                            }
                        }

                        $lastReading = $reading['odometer'];
                    }

                    if(isset($return['less'][$vehicle])) {
                        $return['less'][$vehicle] = array_values($return['less'][$vehicle]);
                    }
                }
                // part 2 end

                return $return['less'];
            }
        }

        // \Log::info('return... '.print_r($return, true));
        return [];
    }

    public function tyreRemovalRecord($userId, $type) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();
        $return['only_in'] = array();
        $return['only_out'] = array();
        $return['conflict'] = array();

        if(!empty($sheet)) {

            if($type == 'only_in') {
                // part 1
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and
                                (
                                    (out_reason is null or out_reason = "")
                                    and (out_size is null or out_size = "")
                                    and (out_brand is null or out_brand = "")
                                    and (out_pattern is null or out_pattern = "")
                                    and (out_retread_brand is null or out_retread_brand = "")
                                    and (out_retread_pattern is null or out_retread_pattern = "")
                                    and (out_serial_no is null or out_serial_no = "")
                                    and (out_job_card_no is null or out_job_card_no = "")
                                    and (out_rtd is null or out_rtd = "")
                                )
                                and 
                                (
                                    (in_attr is not null and in_attr != "")
                                    or (in_price is not null and in_price != "")
                                    or (in_size is not null and in_size != "")
                                    or (in_brand is not null and in_brand != "")
                                    or (in_pattern is not null and in_pattern != "")
                                    or (in_retread_brand is not null and in_retread_brand != "")
                                    or (in_retread_pattern is not null and in_retread_pattern != "")
                                    or (in_serial_no is not null and in_serial_no != "")
                                    or (in_job_card_no is not null and in_job_card_no != "")
                                )
                                order by line_number asc
                            ');

                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    $return['only_in'][] = array(
                        'line'      => $jobsheet->line_number,
                        'jobsheet'  => $jobsheet->jobsheet_no,
                        'type'      => $jobsheet->jobsheet_type,
                        'customer'  => $jobsheet->customer_name,
                        'vehicle'   => $vehicle,
                        'position'  => $jobsheet->position,
                        'remark'    => 'Only Tyre In'
                    );
                }
                // part 1 end

                return $return['only_in'];
            }

            if($type == 'only_out') {
                // part 2
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and
                                (
                                    (in_attr is null or in_attr = "")
                                    and (in_price is null or in_price = "")
                                    and (in_size is null or in_size = "")
                                    and (in_pattern is null or in_pattern = "")
                                    and (in_retread_brand is null or in_retread_brand = "")
                                    and (in_retread_pattern is null or in_retread_pattern = "")
                                    and (in_serial_no is null or in_serial_no = "")
                                    and (in_job_card_no is null or in_job_card_no = "")
                                )
                                and 
                                (
                                    (out_reason is not null and out_reason != "")
                                    or (out_size is not null and out_size != "")
                                    or (out_brand is not null and out_brand != "")
                                    or (out_pattern is not null and out_pattern != "")
                                    or (out_retread_brand is not null and out_retread_brand != "")
                                    or (out_retread_pattern is not null and out_retread_pattern != "")
                                    or (out_serial_no is not null and out_serial_no != "")
                                    or (out_job_card_no is not null and out_job_card_no != "")
                                    or (out_rtd is not null and out_rtd != "")
                                )
                                order by line_number asc
                            ');

                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    $return['only_out'][] = array(
                        'line'      => $jobsheet->line_number,
                        'jobsheet'  => $jobsheet->jobsheet_no,
                        'type'      => $jobsheet->jobsheet_type,
                        'customer'  => $jobsheet->customer_name,
                        'vehicle'   => $vehicle,
                        'position'  => $jobsheet->position,
                        'remark'    => 'Only Tyre Out'
                    );
                }
                // part 2 end

                return $return['only_out'];
            }

            if($type == 'conflict') {
                // part 3
                $jobsheets = \DB::select('
                                select * from data
                                where sheet_id = '.$sheet->id.'
                                and jobsheet_date is not null
                                and position is not null and position != ""
                                order by jobsheet_date asc
                            ');
                
                $vehicles = array();
                foreach($jobsheets as $jobsheet) {
                    $vehicle = $this->getVehicleInfo($jobsheet);

                    if(!empty($vehicle)) {
                        if(!empty($jobsheet->out_serial_no) && isset($vehicles[$vehicle])) {
                            $tyre = $this->getTyreInfo($jobsheet, 'out', true);

                            $vehicles[$vehicle][$jobsheet->position][] = array(
                                'type'      => 'out',
                                'serialNo'  => $jobsheet->out_serial_no,
                                'info'      => 'Date '.Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' Pos '.Helper::formatEmpty($jobsheet->position).', Remove: '.Helper::formatEmpty($tyre).', '.Helper::formatEmpty($jobsheet->out_serial_no)
                            );
                        }

                        if(!empty($jobsheet->in_serial_no)) {
                            $tyre = $this->getTyreInfo($jobsheet, 'in', true);

                            $vehicles[$vehicle][$jobsheet->position][] = array(
                                'type'      => 'in',
                                'serialNo'  => $jobsheet->in_serial_no,
                                'info'      => 'Date '.Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).' @'.Helper::formatEmpty($jobsheet->jobsheet_no).' Pos '.Helper::formatEmpty($jobsheet->position).', Fitting: '.Helper::formatEmpty($tyre).', '.Helper::formatEmpty($jobsheet->in_serial_no)
                            );
                        }
                    }
                }

                foreach($vehicles as $vehicle => $positions) {
                    foreach($positions as $position => $fittings) {
                        if(count($fittings) <= 1) continue;

                        foreach($fittings as $index => $fitting) {
                            if($index > 0) {
                                if($fitting['type'] == 'out' && $lastFittingType == 'in' && $fitting['serialNo'] != $lastSerialNo) {
                                    $return['conflict'][$vehicle][$index-1] = [
                                        'info'      => $fittings[$index-1]['info'],
                                        'remark'    => 'Serial No. Mismatch'
                                    ]; 
                                    $return['conflict'][$vehicle][$index] = [
                                        'info'      => $fittings[$index]['info'],
                                        'remark'    => 'Serial No. Mismatch'
                                    ];
                                }
                            }

                            $lastFittingType    = $fitting['type'];
                            $lastSerialNo       = $fitting['serialNo'];
                        }

                        if(isset($return['conflict'][$vehicle])) {
                            $return['conflict'][$vehicle] = array_values($return['conflict'][$vehicle]);
                        }
                    }
                }
                // part 3 end

                return $return['conflict'];
            }
        }

        // \Log::info('return... '.print_r($return['conflict'], true));
        return [];
    }

    public function tyreRemovalMileage($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();

        if(!empty($sheet)) {
            $jobsheets = \DB::select('
                            select * from data
                            where sheet_id = '.$sheet->id.'
                            and
                            (in_serial_no is not null and in_serial_no != "")
                            or 
                            (out_serial_no is not null and out_serial_no != "")
                            order by jobsheet_date asc
                        ');

            foreach($jobsheets as $jobsheet) {
                $vehicle = $this->getVehicleInfo($jobsheet);

                if(!empty($jobsheet->in_serial_no)) {
                    $tyre = $this->getTyreInfo($jobsheet, 'in');
                    $tyreRetread = $this->getTyreRetreadInfo($jobsheet, 'in');

                    $return[$jobsheet->in_serial_no][] = array(
                        'tyre'          => $tyre,
                        'tyre_retread'  => $tyreRetread,
                        'in_out'        => 'Tyre In',
                        'remark'        => $jobsheet->in_attr,
                        'date'          => Helper::formatDate($jobsheet->jobsheet_date),
                        'jobsheet'      => $jobsheet->jobsheet_no,
                        'vehicle'       => $vehicle,
                        'position'      => $jobsheet->position,
                        'odometer'      => $jobsheet->odometer,
                        'mileage'       => ''
                    );
                }

                if(!empty($jobsheet->out_serial_no)) {
                    $tyre = $this->getTyreInfo($jobsheet, 'out');
                    $tyreRetread = $this->getTyreRetreadInfo($jobsheet, 'out');

                    $return[$jobsheet->out_serial_no][] = array(
                        'tyre'          => $tyre,
                        'tyre_retread'  => $tyreRetread,
                        'in_out'        => 'Tyre Out',
                        'remark'        => $jobsheet->out_reason,
                        'date'          => Helper::formatDate($jobsheet->jobsheet_date),
                        'jobsheet'      => $jobsheet->jobsheet_no,
                        'vehicle'       => $vehicle,
                        'position'      => $jobsheet->position,
                        'odometer'      => $jobsheet->odometer,
                        'mileage'       => '-'
                    );

                    $fittingTimes = count($return[$jobsheet->out_serial_no]);
                    if($fittingTimes > 1) {
                        if(isset($return[$jobsheet->out_serial_no][$fittingTimes-2])) {
                            if(
                                $return[$jobsheet->out_serial_no][$fittingTimes-2]['vehicle'] == $return[$jobsheet->out_serial_no][$fittingTimes-1]['vehicle'] &&
                                $return[$jobsheet->out_serial_no][$fittingTimes-2]['position'] == $return[$jobsheet->out_serial_no][$fittingTimes-1]['position'] &&
                                $return[$jobsheet->out_serial_no][$fittingTimes-2]['in_out'] == 'Tyre In'
                            ) { 
                                $lastOdometer   = $return[$jobsheet->out_serial_no][$fittingTimes-2]['odometer'];
                                $odometer       = $return[$jobsheet->out_serial_no][$fittingTimes-1]['odometer'];

                                $return[$jobsheet->out_serial_no][$fittingTimes-1]['mileage'] = $odometer - $lastOdometer;
                            }
                        }
                    }
                }
            }
        }

        // \Log::info('return... '.print_r($return, true));
        return $return;
    }

    public function getVehicleInfo($jobsheet) {
        $vehicle = array();
        if(!empty($jobsheet->truck_no)) array_push($vehicle, $jobsheet->truck_no);
        if(!empty($jobsheet->pm_no)) array_push($vehicle, $jobsheet->pm_no);
        if(!empty($jobsheet->trailer_no)) array_push($vehicle, $jobsheet->trailer_no);
        $vehicle = implode($vehicle, ', ');

        return $vehicle;
    }

    public function getTyreInfo($jobsheet, $type, $retreadFlag = false, $attrReasonFlag = false, $sizeFlag = false, $serialNoFlag = false) {
        if($type == 'in') {
            $brand              = 'in_brand';
            $pattern            = 'in_pattern';
            $retreadBrand       = 'in_retread_brand';
            $retreadPattern     = 'in_retread_pattern';
            $size               = 'in_size';
            $serialNo           = 'in_serial_no';
            if($attrReasonFlag == true) $attrReason = 'in_attr';
            
        } elseif ($type == 'out') {
            $brand              = 'out_brand';
            $pattern            = 'out_pattern';
            $retreadBrand       = 'out_retread_brand';
            $retreadPattern     = 'out_retread_pattern';
            $size               = 'out_size';
            $serialNo           = 'out_serial_no';
            if($attrReasonFlag == true) $attrReason = 'out_reason';
        }

        $tyre = array();
        if($attrReasonFlag == true) {
            if(!empty($jobsheet->$attrReason)) array_push($tyre, $jobsheet->$attrReason);
        }
        if(!empty($jobsheet->$brand)) array_push($tyre, $jobsheet->$brand);
        if(!empty($jobsheet->$pattern)) array_push($tyre, $jobsheet->$pattern);
        if($retreadFlag == true) {
            if(!empty($jobsheet->$retreadBrand)) array_push($tyre, $jobsheet->$retreadBrand);
            if(!empty($jobsheet->$retreadPattern)) array_push($tyre, $jobsheet->$retreadPattern);
        }
        if($sizeFlag == true) {
            if(!empty($jobsheet->$size)) array_push($tyre, $jobsheet->$size);
        }
        if($serialNoFlag == true) {
            if(!empty($jobsheet->$serialNo)) array_push($tyre, $jobsheet->$serialNo);
        }
        $tyre = implode($tyre, ', ');

        return $tyre;
    }

    public function getTyreRetreadInfo($jobsheet, $type) {
        if($type == 'in') {
            $brand      = 'in_retread_brand';
            $pattern    = 'in_retread_pattern';
        } elseif ($type == 'out') {
            $brand      = 'out_retread_brand';
            $pattern    = 'out_retread_pattern';
        }

        $tyre = array();
        if(!empty($jobsheet->$brand)) array_push($tyre, $jobsheet->$brand);
        if(!empty($jobsheet->$pattern)) array_push($tyre, $jobsheet->$pattern);
        $tyre = implode($tyre, ', ');

        return $tyre;
    }

    public function truckTyreCost($userId, $sort, $limit) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();

        if(!empty($sheet)) {

            $rows = \DB::select('
                        select 
                            customer_name, min(jobsheet_date) as from_date, max(jobsheet_date) as to_date
                        from tyreadmin.data
                        where sheet_id = '.$sheet->id.'
                        and customer_name is not null and customer_name != ""
                        and in_price is not null
                        group by customer_name
                        order by customer_name asc;
                    ');

            foreach($rows as $row) {
                $return[$row->customer_name] = array(
                    'from'      => Helper::formatDate($row->from_date),
                    'to'        => Helper::formatDate($row->to_date),
                    'costs'     => array(
                        'pm'        => array(),
                        'trailer'   => array(),
                        'truck'     => array()
                    )
                );
            }

            // part 1
            $rows = \DB::select('
                        select 
                            customer_name, pm_no, sum(in_price) as total_cost
                        from data
                        where sheet_id = '.$sheet->id.'
                        and customer_name is not null and customer_name != ""
                        and pm_no is not null and pm_no != ""
                        and in_price is not null
                        group by customer_name, pm_no
                        order by total_cost '. $sort .', pm_no asc
                    ');

            foreach($rows as $row) {
                if(isset($return[$row->customer_name]['costs']['pm']) && count($return[$row->customer_name]['costs']['pm']) < $limit) {
                    $return[$row->customer_name]['costs']['pm'][] = array(
                        'vehicleNo' => $row->pm_no,
                        'cost' => 'RM'.number_format($row->total_cost, 2)
                    );
                }
            }
            // part 1 end

            // part 2
            $rows = \DB::select('
                        select 
                            customer_name, trailer_no, sum(in_price) as total_cost
                        from data
                        where sheet_id = '.$sheet->id.'
                        and customer_name is not null and customer_name != ""
                        and trailer_no is not null and trailer_no != ""
                        and in_price is not null
                        group by customer_name, trailer_no
                        order by total_cost '. $sort .', trailer_no asc
                    ');

            foreach($rows as $row) {
                if(isset($return[$row->customer_name]['costs']['trailer']) && count($return[$row->customer_name]['costs']['trailer']) < $limit) {
                    $return[$row->customer_name]['costs']['trailer'][] = array(
                        'vehicleNo' => $row->trailer_no,
                        'cost' => 'RM'.number_format($row->total_cost, 2)
                    );
                }
            }
            // part 2 end

            // part 3
            $rows = \DB::select('
                        select 
                            customer_name, truck_no, sum(in_price) as total_cost
                        from data
                        where sheet_id = '.$sheet->id.'
                        and customer_name is not null and customer_name != ""
                        and truck_no is not null and truck_no != ""
                        and in_price is not null
                        group by customer_name, truck_no
                        order by total_cost '. $sort .', truck_no asc
                    ');

            foreach($rows as $row) {
                if(isset($return[$row->customer_name]['costs']['truck']) && count($return[$row->customer_name]['costs']['truck']) < $limit) {
                    $return[$row->customer_name]['costs']['truck'][] = array(
                        'vehicleNo' => $row->truck_no,
                        'cost' => 'RM'.number_format($row->total_cost, 2)
                    );
                }
            }
            // part 3 end
        }

        // \Log::info('return... '.print_r($return, true));
        //die();
        return $return;
    }

    public function truckServiceRecord($userId) {
        $sheet = $this->model->where('user_id', $userId)->first();

        $return = array();

        if(!empty($sheet)) {

            // part 1
            $jobsheets = \DB::select('
                        select * from tyreadmin.data
                        where sheet_id = '.$sheet->id.'
                        and customer_name is not null and customer_name != ""
                        and position is not null and position != ""
                        order by customer_name, position, jobsheet_date asc
                    ');

            foreach($jobsheets as $jobsheet) {
                if(!empty($jobsheet->pm_no)) {
                    $return[$jobsheet->customer_name]['pm'][$jobsheet->pm_no][$jobsheet->position][] = array(
                        'info'  => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).': '.Helper::formatEmpty($jobsheet->odometer).'KM ('.Helper::formatEmpty($jobsheet->jobsheet_type).') @'.Helper::formatEmpty($jobsheet->jobsheet_no),
                        'in'    => Helper::formatEmpty($jobsheet->in_attr).' RM'.number_format($jobsheet->in_price, 2).Helper::formatEmpty($jobsheet->in_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'in', 'true')),
                        'out'   => Helper::formatEmpty($jobsheet->out_reason).', '.Helper::formatEmpty($jobsheet->out_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'out', 'true')).', '.Helper::formatEmpty($jobsheet->out_rtd).'mm'
                    );
                }

                if(!empty($jobsheet->trailer_no)) {
                    $return[$jobsheet->customer_name]['trailer'][$jobsheet->trailer_no][$jobsheet->position][] = array(
                        'info'  => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).': '.Helper::formatEmpty($jobsheet->odometer).'KM ('.Helper::formatEmpty($jobsheet->jobsheet_type).') @'.Helper::formatEmpty($jobsheet->jobsheet_no),
                        'in'    => Helper::formatEmpty($jobsheet->in_attr).' RM'.number_format($jobsheet->in_price, 2).Helper::formatEmpty($jobsheet->in_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'in', 'true')),
                        'out'   => Helper::formatEmpty($jobsheet->out_reason).', '.Helper::formatEmpty($jobsheet->out_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'out', 'true')).', '.Helper::formatEmpty($jobsheet->out_rtd).'mm'
                    );
                }

                if(!empty($jobsheet->truck_no)) {
                    $return[$jobsheet->customer_name]['truck'][$jobsheet->truck_no][$jobsheet->position][] = array(
                        'info'  => Helper::formatEmpty(Helper::formatDate($jobsheet->jobsheet_date)).': '.Helper::formatEmpty($jobsheet->odometer).'KM ('.Helper::formatEmpty($jobsheet->jobsheet_type).') @'.Helper::formatEmpty($jobsheet->jobsheet_no),
                        'in'    => Helper::formatEmpty($jobsheet->in_attr).' RM'.number_format($jobsheet->in_price, 2).Helper::formatEmpty($jobsheet->in_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'in', 'true')),
                        'out'   => Helper::formatEmpty($jobsheet->out_reason).', '.Helper::formatEmpty($jobsheet->out_size).' '.Helper::formatEmpty($this->getTyreInfo($jobsheet, 'out', 'true')).', '.Helper::formatEmpty($jobsheet->out_rtd).'mm'
                    );
                }
            }
            // part 1 end
        }

        // \Log::info('return... '.print_r($return, true));
        return $return;
    }
}
