<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Admin\Order;
use App\Models\Admin\OrderItem;
use App\Models\Admin\ThirdPartyReport;
use Carbon\Carbon;
use Log;

class UpdateTpReportStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #sample.  php artisan command:updateTpReportStatus
    protected $signature = 'command:updateTpReportStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the verified record to complete after merchant payment report done.';

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
        $startDateTime = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        $endDateTime = Carbon::now()->endOfMonth()->format('Y-m-d H:i:s');
        $date_range = [$startDateTime, $endDateTime];
        $items = OrderItem::chargeable()->leftJoin(
        \DB::raw("
            (select
                `order_status_log`.`order_id`,`order_status_log`.`to_status`,`order_status_log`.`created_at` as completed_date
            from `order_status_log`
            where `order_status_log`.`to_status` = 'Completed'
            and `order_status_log`.`user_id` = 0 group by order_id
            ) `order_completed`

        "), 'order_completed.order_id', '=', 'order_items.order_id')
        ->select('order_items.*', 'third_party_report.channel_fees', 'third_party_report.id as tp_id')
        ->leftJoin('third_party_report','third_party_report.order_item_id','=','order_items.id')
        ->whereBetween('third_party_report.payment_date', $date_range) 
        ->where('third_party_report.item_status', \DB::raw('order_items.status'))
        ->where('third_party_report.status', 'Verified')
        ->where('third_party_report.paid_status', 1)
        ->get();

        foreach ($items as $item) {
            Log::info('Updating '.$item->tp_id.' status to Completed');
            ThirdPartyReport::where('id', '=', $item->tp_id)->update(['status' => 'Completed']);
        }
    }


}
