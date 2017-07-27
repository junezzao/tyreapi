<?php

namespace App\Modules\Reports\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Admin\Order;
use App\Models\Admin\Merchant;
use App\Models\Admin\SKU;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Channel;
use App\Models\Admin\Product;
use Excel;
use App\Services\Mailer;
use Log;
use DB;
use Storage;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Config;

class GenerateSalesReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generateSalesReport {startdate} {enddate} {emails} {duration?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate the Sales Report';

    protected $mailer;

    protected $reportsDirectory = 'reports';
    private $test_merchant = "Test Merchant";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Mailer $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $startDate = $this->argument('startdate');
        $endDate = $this->argument('enddate');
        $duration = ($this->argument('duration') ? $this->argument('duration') : '');
        $emails = $this->argument('emails');
        $gst = config('globals.GST');

        Log::info('Begin generation Sales Report at ' . Carbon::now());
        $this->info('Generating sales report from '. $startDate . ' to '. $endDate);


        // get all sales for the duration
        ini_set('memory_limit','-1');
        $dateRange = [$startDate, $endDate];

        $sales = Order::whereBetween('shipped_date', $dateRange)
                            ->where('status', Order::$completedStatus)
                            ->get();

        $threeMonthsBefore = Carbon::createFromFormat('Y-m-d H:i:s', $endDate)->subMonths(3);

        $merchants = Merchant::select('id', 'name', 'slug')
                        ->where('status', '=', 'Active')
                        ->orWhere(function ($query) use ($threeMonthsBefore) {
                                    $query->where('status', '=', 'Inactive')
                                        ->where('deactivated_date', '>', $threeMonthsBefore);
                                })
                        ->get()
                        ->keyBy('id');
        $merchants->prepend(array('id' => 0, 'name' => 'Deactivated', 'slug' => 'Deactivated Merchants'), 0);
        $merchants = $merchants->toArray();

        $saleMasterData = array();

        foreach ($sales as $sale) {
            $items = $sale->itemSKUs;
            $itemArr = array();

            foreach ($items as $item) {

                if ($item->isChargeable()) {
                    if($item->tax_inclusive == true) {
                        $soldAmount = $item->sold_price;
                        $soldAmountWithoutGst = $item->sold_price - $item->tax;
                    } else if($item->tax_inclusive == false) {
                        $soldAmount = $item->sold_price + $item->tax;
                        $soldAmountWithoutGst = $item->sold_price;
                    }
                    $discount = $item->sale_price > 0 ? $item->unit_price - $item->sale_price : 0;
                    $channel_sku = ChannelSKU::find($item->ref_id);

                    $itemArr[] = array(
                        'sku_id'                        => $item->ref->sku->sku_id,
                        'hw_fee'                        => $item->hw_fee,
                        'min_guarantee'                 => $item->min_guarantee,
                        'channel_fee'                   => $item->channel_fee,
                        'channel_mg'                    => $item->channel_mg,
                        'channel_id'                    => $channel_sku->channel_id,
                        'unit_price'                    => $item->unit_price,
                        'sale_price'                    => ($item->sale_price == 0)?$item->unit_price:$item->sale_price,
                        'total_amount_paid'             => $soldAmount * $item->original_quantity,
                        'total_amount_paid_excl_gst'    => $soldAmountWithoutGst * $item->original_quantity,
                        'total_discount'                => $discount * $item->original_quantity,
                        'total_quantity'                => $item->original_quantity,
                        'consignment_no'                => $item->order->consignment_no,
                        'merchant_shipping_fee'         => $item->merchant_shipping_fee,
                    );
                }
            }

            foreach ($itemArr as $itemTotals) {
                $sku = SKU::find($itemTotals['sku_id']);
                $product = Product::withTrashed()->with('brands', 'merchant', 'category')->find($sku->product_id);
                $channel = Channel::with('channel_type')->find($itemTotals['channel_id']);
                if(strcmp($product->merchant->name,$this->test_merchant)==0){
                    continue;
                }

                $get_issuing_companies = json_decode(json_encode(DB::table('issuing_companies')->where('id', $channel->issuing_company)->first()), true);
                $gst_reg = $get_issuing_companies['gst_reg'];
                $category = '';

                if (isset($product->category->full_name) && !empty($product->category->full_name)) {
                    $category = explode('/', $product->category->full_name);
                }

                $reportData = [
                    'Merchant'                          => $product->merchant->name,
                    'Channel'                           => $channel->name,
                    'Channel Type'                      => $channel->channel_type->name,
                    'Third Party Order Date'            => (!is_null($sale->tp_order_date)) ? Carbon::createFromFormat('Y-m-d H:i:s', $sale->tp_order_date)->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s') : '',
                    'Order Completed Date'              => !($sale->orderDate($sale->id)) ? $sale->orderDate($sale->id) : Carbon::createFromFormat('Y-m-d H:i:s', $sale->orderDate($sale->id))->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s'),
                    'Order No'                          => $sale->id,
                    'Third Party Order No'              => $sale->tp_order_code,
                    'Brand'                             => $product->getRelation('brands')->name,
                    'Hubwire SKU'                       => $sku->hubwire_sku,
                    'Supplier SKU'                      => $sku->sku_supplier_code,
                    'Product Name'                      => $product->name,
                    'Main Category'                     => isset($category[0]) ? $category[0] : '',
                    'Sub-category'                      => isset($category[1]) ? $category[1] : '',
                    'Sub-subcategory'                   => isset($category[2]) ? $category[2] : '',
                    'Size'                              => $sku->size,
                    'Color'                             => $sku->color,
                    'Quantity'                          => $itemTotals['total_quantity'],
                    'Currency'                          => $sale->currency,
                    'Retail Price (Incl. GST)'          => ($gst_reg == 1) ? number_format($itemTotals['unit_price'], 2) : number_format($itemTotals['unit_price']/$gst, 2),
                    'Retail Price (Excl. GST)'          => number_format($itemTotals['unit_price']/$gst, 2),
                    'Listing Price (Incl. GST)'         => ($gst_reg == 1) ? number_format($itemTotals['sale_price'], 2) : number_format($itemTotals['sale_price']/$gst, 2),
                    'Listing Price (Excl. GST)'         => number_format($itemTotals['sale_price']/$gst, 2),
                    'Discounts'                         => number_format($itemTotals['total_discount'], 2), // sum of all the quantities
                    'Total Sales (Incl. GST)'           => ($gst_reg == 1) ? number_format($itemTotals['total_amount_paid'], 2) : number_format($itemTotals['total_amount_paid'], 2),
                    'Total Sales (Excl. GST)'           => ($gst_reg == 1) ? number_format($itemTotals['total_amount_paid_excl_gst'], 2) : number_format($itemTotals['total_amount_paid'], 2),
                    'Arc Shipping Fee'                  => $itemTotals['merchant_shipping_fee'],
                ];

                if (strcasecmp($duration, 'monthly') == 0) {
                    $reportData['FM Hubwire Fee (Excl. GST)']           = number_format($itemTotals['hw_fee'] / $gst, 3);
                    $reportData['Minimum Guarantee (Excl. GST)']        = number_format($itemTotals['min_guarantee'] / $gst, 3);
                    $reportData['Channel Fee (Excl. GST)']              = number_format($itemTotals['channel_fee'] / $gst, 3);
                    $reportData['Channel Min Guarantee (Excl. GST)']    = number_format($itemTotals['channel_mg'] / $gst, 3);
                }

                $reportData['Consignment Number'] = $itemTotals['consignment_no'];

                // if merchant/brand is active or has been deactivated for less than three months
                if ((array_key_exists($product->merchant_id, $merchants) || $product->merchant->deactivated_date > $threeMonthsBefore) &&
                    ($product->getRelation('brands')->active || $product->getRelation('brands')->deactivated_date > $threeMonthsBefore)) {
                    $saleMasterData[] = $reportData;
                    $merchants[$product->merchant_id]['sales'][] = $reportData;
                }
                else {
                    // deactivated merchant and/or brand sales
                    $merchants[0]['sales'][] = $reportData;
                }
            }
        }

        // generate excel file
        $filename = 'sales_report_'.$startDate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'-'.$endDate->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Ymd').'_'.Carbon::now()->format('YmdHis'); // timestamp in UTC
        $cellsTop = (strcasecmp($duration, 'monthly') == 0) ? 'A1:AE1' : 'A1:AA1';
        $cellsThird = (strcasecmp($duration, 'monthly') == 0) ? 'A3:AE3' : 'A3:AA3';

        $excel = Excel::create($filename, function($excel) use($saleMasterData, $merchants, $startDate, $endDate, $duration, $cellsTop, $cellsThird) {

            $excel->sheet('Master List', function($sheet) use($saleMasterData, $startDate, $endDate, $duration, $cellsTop, $cellsThird) {

                $sheet->fromArray($saleMasterData, null, 'A1', true);

                $sheet->prependRow( array('') );
                $sheet->prependRow(
                            array($duration . ' Sales Report ('.$startDate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString() .' - '.$endDate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString() .')')
                        );

                $sheet->cells($cellsTop, function($cells) {
                    $cells->setBackground('#205081');
                    $cells->setFontColor('#ffffff');
                    $cells->setFont(array(
                        'size'       => '16',
                        'bold'       =>  true
                    ));
                });

                $sheet->cells($cellsThird, function($cells) {
                    $cells->setFont(array(
                        'bold'       =>  true
                    ));
                });

                $sheet->cells('H3:L'.(sizeof($saleMasterData)+3), function($cells) {
                    $cells->setAlignment('left');
                });

                $sheet->setColumnFormat(array( 'W' => 'text' ));
            });

            foreach ($merchants as $merchant) {
                if(!empty($merchant['sales'])){
                    $sheetName = $merchant['slug'];
                    $excel->sheet($sheetName, function($sheet) use($merchant, $startDate, $endDate, $duration, $cellsTop, $cellsThird) {

                        if(array_key_exists('sales', $merchant)){
                            $sheet->fromArray($merchant['sales'], null, 'A1', true);
                        }

                        $sheet->prependRow( array('') );
                        $sheet->prependRow(
                                    array($duration . ' ' . $merchant['name'] .' Sales Report ('.$startDate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString() .' - '.$endDate->copy()->setTimezone('Asia/Kuala_Lumpur')->toDateString() .')')
                                );

                        $sheet->cells($cellsTop, function($cells) {
                            $cells->setBackground('#205081');
                            $cells->setFontColor('#ffffff');
                            $cells->setFont(array(
                                'size'       => '16',
                                'bold'       =>  true
                            ));
                        });

                        $sheet->cells($cellsThird, function($cells) {
                            $cells->setFont(array(
                                'bold'       =>  true
                            ));
                        });

                        $sheet->cells('H3:L'.(sizeof($merchant['sales'])+3), function($cells) {
                            $cells->setAlignment('left');
                        });

                        $sheet->setColumnFormat(array( 'W' => 'text' ));
                    });
                }
            }
            $excel->setActiveSheetIndex(0);
        })->store('xls', storage_path('app/reports'), true);

        // move file to S3
        $uploadedfile = Storage::disk('local')->get('reports/'.$excel['file']);
        $s3path = $this->reportsDirectory.'/Sales/'.$endDate->format('Y').'/'.$endDate->format('m').'/'.$excel['file'];
        $s3upload = Storage::disk('s3')->put($s3path, $uploadedfile);
        if($s3upload){
            Storage::disk('local')->delete('reports/'.$excel['file']);

            $email_data['merchant_name'] = (!empty($merchant) ? $merchant->name : '');
            $email_data['url'] = env('AWS_S3_URL').$s3path;
            $email_data['email'] = $emails;
            $email_data['subject'] = $duration . ' Sales Report ('.$startDate->format('Y-m-d').' - '.$endDate->format('Y-m-d').')';
            $email_data['report_type'] = 'sales';
            $email_data['duration'] = $duration;
            $email_data['startdate'] = $startDate->format('Y-m-d');
            $email_data['enddate'] = $endDate->format('Y-m-d');
            $this->mailer->scheduledReport($email_data);
        }
        Log::info('End generating and emailing '.$duration.' Sales Report ('.$startDate->format('Y-m-d').' - '.$endDate->format('Y-m-d').') at '. Carbon::now());
    }
}
