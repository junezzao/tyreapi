<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\Brand;
use App\Models\Admin\Merchant;
use App\Models\Admin\Contract;
use Carbon\Carbon;

class DeactivateBrandsAndMerchants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deactivate:brandsAndMerchants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate brands and merchants';

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
        //
        $threeMonthsBefore = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now()->toDateTimeString())->setTimezone('UTC')->subMonths(3);

        Brand::chunk(1000, function($brands) use ($threeMonthsBefore) {
            foreach($brands as $brand) {
                // if brand has been inactive for at least three months
                if (!$brand->active && $brand->deactivated_date <= $threeMonthsBefore) {
                    $brand->delete();
                    $this->info('Deactivated brand #'. $brand->id);

                    // find and deactivate all active contracts for that brand
                    $contracts = Contract::where('brand_id', '=', $brand->id)
                                ->where('status', '=', 1)
                                ->get();

                    foreach ($contracts as $contract) {
                        $contract->status = 0;
                        $contract->save();
                        $this->info('Deactivated contract #'. $contract->id);
                    }
                }
            }
        });

        Merchant::where('status', '=', 'Active')->chunk(1000, function($merchants) {
            foreach($merchants as $merchant) {             
                // if merchant has no active brands
                $associatedBrands = Brand::where('merchant_id', '=', $merchant->id)->count();
                if ($associatedBrands == 0) {
                    $merchant->status = 'Inactive';
                    $merchant->deactivated_date = Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now()->toDateTimeString())->setTimezone('UTC');
                    $merchant->save();
                    $this->info('Deactivated merchant #'. $merchant->id);
                }
            }
        });
    }
}