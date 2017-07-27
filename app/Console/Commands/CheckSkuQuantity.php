<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ChannelSkuSnapshot;
use App\Models\Admin\SKUQuantityLog;
use Carbon\Carbon;
use DB;
use Monolog;

class CheckSkuQuantity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sku:checkQuantity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "To tally the quantities of skus' balance against the daily stock cache.";

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
        $skuLog = new Monolog\Logger('Sku Log');
        $skuLog->pushHandler(new Monolog\Handler\StreamHandler(storage_path().'/logs/sku.log', Monolog\Logger::INFO));

        $yesterday = Carbon::today()->toDateString() . ' 15:59:59';
        $this->info('Checking skus against logs older than ' . $yesterday . ' ...');

        ChannelSkuSnapshot::select('sku_id', DB::raw("SUM(channel_sku_quantity) as sku_quantity"))
                            ->groupBy('sku_id')
                            ->chunk(1000, function ($skus) use ($yesterday, $skuLog) {
                                foreach ($skus->toArray() as $sku) {
                                    $latestLog = SKUQuantityLog::where('sku_id', '=', $sku['sku_id'])
                                                                ->where('created_at', '<=', $yesterday)
                                                                ->orderBy('created_at', 'desc')
                                                                ->first();

                                    if ($latestLog->quantity != $sku['sku_quantity']) {
                                        $skuLog->addError('Sku ' . $sku['sku_id'] . ' quantity discrepancy: log ' . $latestLog->id . ' quantity = ' . $latestLog->quantity . ', snapshot quantity: ' . $sku['sku_quantity']);
                                        $newLog = SKUQuantityLog::create(array(
                                            'sku_id'        => $sku['sku_id'],
                                            'quantity'      => $sku['sku_quantity'],
                                            'created_at'    => $yesterday,
                                            'updated_at'    => $yesterday
                                        ));
                                        $skuLog->addInfo('Adjustment log ' . $newLog->id . ' created.');
                                    }
                                }
                            });

        $this->info('Done.');
    }
}
