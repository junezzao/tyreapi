<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Repositories\Eloquent\OrderItemRepository;
use App\Models\Admin\Order;
use Monolog;

class CalculateFee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    //example : php artisan calculate:fees "HubwireFee" --start_date=2017-02-01 --end_date=2017-02-28
    protected $signature = 'calculate:fees
                                {feeType : Fee type ("HubwireFee" or "ChannelFee")}
                                {--start_date= : Start date in format Y-m-d}
                                {--end_date= : End date in format Y-m-d}
                                {merchant?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate hubwire/channel fee and minimum guarantee (if applicable).';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $feeType = $this->argument('feeType');
            $merchantId = ($this->argument('merchant') != '' ? $this->argument('merchant') : '');

            if ($feeType != "HubwireFee" && $feeType != "ChannelFee") {
                $this->error('Fee type must be either "HubwireFee" or "ChannelFee"');
                return;
            }

            $feeTypeInfo = array(
                'HubwireFee'    => array(
                    'log_name'          => "Hubwire Fee Log",
                    'log_file_path'     => "/logs/hubwire_fee.log",
                    'contract_class'    => "Contract",
                ),
                'ChannelFee'    => array(
                    'log_name'          => "Channel Fee Log",
                    'log_file_path'     => "/logs/channel_fee.log",
                    'contract_class'    => "ChannelContract",
                ),
            );

            $customLog = new Monolog\Logger($feeTypeInfo[$feeType]['log_name']);
            $customLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path() . $feeTypeInfo[$feeType]['log_file_path'], Monolog\Logger::INFO));

            if (!is_null($this->option('start_date'))) {
                $startDateTime = Carbon::createFromFormat('Y-m-d', $this->option('start_date'), 'Asia/Kuala_Lumpur')->startOfDay()->setTimezone('UTC');
            }
            else {
                $startDateTime = Carbon::now('Asia/Kuala_Lumpur')->subMonth()->startOfMonth()->setTimezone('UTC');
            }

            if (!is_null($this->option('end_date'))) {
                $endDateTime = Carbon::createFromFormat('Y-m-d', $this->option('end_date'), 'Asia/Kuala_Lumpur')->endOfDay()->setTimezone('UTC');
            }
            else {
                $endDateTime = Carbon::now('Asia/Kuala_Lumpur')->subMonth()->endOfMonth()->setTimezone('UTC');
            }

            $this->info("$feeType will be calculated from UTC $startDateTime to UTC $endDateTime");

            $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $startDateTime)->toDateString();
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $endDateTime)->toDateString();

            $totalBrandOrderItems = array();
            Order::with('chargeableItems')
                ->where('status', '=', Order::$completedStatus)
                ->whereBetween('shipped_date', [$startDateTime, $endDateTime])
                ->chunk(500, function($orders) use ($startDateTime, $endDateTime, $feeTypeInfo, $feeType, $customLog, $merchantId) {
                    foreach ($orders as $order) {
                        foreach ($order->chargeableItems as $item) {
                            $orderItemRepo = new OrderItemRepository($item, true);
                            $orderItemRepo->setDateTimeRange([$startDateTime, $endDateTime]);
                            $orderItemRepo->setContractClass($feeTypeInfo[$feeType]['contract_class']);
                            if (empty($merchantId)) {
                                $response = $orderItemRepo->calculateFee(true, $totalBrandOrderItems);
                                $info = $response['success'] ? $response['response'] : $response['error'];
                                $this->info($info);
                                $customLog->addInfo($info);
                            }elseif ($orderItemRepo->checkMerchantId($merchantId)) {
                                $response = $orderItemRepo->calculateFee(true, $totalBrandOrderItems);
                                $info = $response['success'] ? $response['response'] : $response['error'];
                                $this->info($info);
                                $customLog->addInfo($info);
                            }
                        }
                    }
                });
        }
        catch (Exception $e) {
            $this->error("An error occured: $e->getMessage() at line $e->getLine()");
        }
    }
}
