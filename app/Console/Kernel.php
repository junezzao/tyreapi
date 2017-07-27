<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\Inspire::class,
        Commands\updateProdToEs::class,
        Commands\Pull11StreetOrder::class,
        Commands\PullZaloraOrder::class,
        Commands\PullLazadaOrder::class,
        Commands\PullMissingSalesIntoOrdersTable::class,
        Commands\GenerateReports::class,
        Commands\UpdateOrderStatusToCompleted::class,
        \App\Modules\Reports\Console\Commands\GenerateReturnsReport::class,
        \App\Modules\Reports\Console\Commands\GenerateSalesReport::class,
        \App\Modules\Reports\Console\Commands\GenerateInventoryReport::class,
        \App\Modules\Reports\Console\Commands\GenerateTaxInvoice::class,
        Commands\CustomFieldsElasticSearch::class,
        Commands\MigrateOldCustomFieldsData::class,
        Commands\SyncThirdParty::class,
        Commands\PullMissingSalesIntoOrdersTable::class,
        Commands\StockTransferCorrectionMigration::class,
        Commands\pullCategoryFromS3::class,
        Commands\moveDocumentsOnS3::class,
        Commands\pullSellerCenterConsignment::class,
        Commands\ShopifyPullOrders::class,
        Commands\PullShopeeOrder::class,
        Commands\dailyStockCache::class,
        Commands\GenerateDashboardStats::class,
        Commands\ArchiveSyncs::class,
        // Commands\PopulateReservedQuantities::class, // removed command as regenerating the reserved quantities will affect reporting. Command is only for reference.
        Commands\PatchProductThirdPartyMediaSync::class,
        Commands\FailedOrders::class,
        Commands\CriticalOrders::class,
        Commands\ManualUpdateQuantitySync::class,
        Commands\RepopulateReservedQuantities::class,
        Commands\FailedOrders::class,
        Commands\CriticalOrders::class,
        Commands\ChangeChannelType::class,
        Commands\alertSaleExpiry::class,
        Commands\ElevenStCheckProductQty::class,
        Commands\ShopifyCheckProductQty::class,
        Commands\SellerCenterCheckProductQty::class,
        Commands\LazadaCheckProductQty::class,
        Commands\CheckSkuQuantity::class,
        Commands\ResetDashboardCounters::class,
        Commands\updateOrdersToEs::class,
        Commands\DeactivateBrandsAndMerchants::class,
        Commands\CheckMarketplacesOrder::class,
        Commands\CalculateFee::class,
        \App\Modules\Reports\Console\Commands\GenerateMerchantSalesInventoryReport::class,
        \App\Modules\Reports\Console\Commands\PushMerchantSalesInventoryReport::class,
        Commands\MoveShopifyTpReportOrder::class,
        Commands\CompareChannelFeeChannelMg::class,
        Commands\removeDuplicateChannelSku::class,
        Commands\UpdateTpReportStatus::class,
        \App\Modules\Reports\Console\Commands\GenerateChannelMerchantPaymentReport::class,
        \App\Modules\Reports\Console\Commands\GenerateMerchantPaymentReport::class,
        \App\Modules\Reports\Console\Commands\GenerateMerchantInvoiceReport::class,
        Commands\CalculateShippingFee::class,
        \App\Modules\Reports\Console\Commands\GenerateMerchantStorageReport::class,
        Commands\createLivePriceSync::class,
        Commands\MigrateOldChnlSkuCF::class,
        \App\Modules\Reports\Console\Commands\GenerateMerchantStorageReport::class,
        Commands\ShopifyUpdateSku::class,
        Commands\MigrateOldChnlSkuCF::class,
        Commands\duplicateProductSkuInfo::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //         ->hourly();

        $schedule->command('statistics:resetDashboardCounters')
                 ->dailyAt('16:00');

        $schedule->command('command:updateOrderStatusToCompleted')
                 ->dailyAt('16:00');

        $schedule->command('report:dailyStockCache')
                 ->dailyAt('16:00');

        $schedule->command('sku:checkQuantity')
                 ->dailyAt('16:00');

        $schedule->command('reports:TaxInvoice daily')
                 ->dailyAt('16:00');

        $schedule->command('deactivate:brandsAndMerchants')
                 ->monthlyOn(4, '16:00');

        $schedule->command('reports:generateReports "reports:generateSalesReport" daily')
                 ->dailyAt('16:00');

        $schedule->command('reports:generateReports "reports:generateSalesReport" weekly')
                 ->sundays()->at('16:00');

        $schedule->command('calculate:fees HubwireFee')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('calculate:fees ChannelFee')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('command:moveShopifyTpReportOrder')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('command:compareChannelFeeChannelMg')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('reports:generateReports "reports:generateSalesReport" monthly')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('reports:generateReports "reports:generateReturnsReport" weekly')
                 ->sundays()->at('16:00');

        $schedule->command('reports:generateReports "reports:generateReturnsReport" monthly')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('reports:generateReports "reports:generateInventoryReport" weekly')
                 ->sundays()->at('16:00');

        $schedule->command('reports:generateReports "reports:generateInventoryReport" monthly')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        $schedule->command('reports:generateReports "reports:generateMerchantSalesInventoryReport" weekly')
                 ->sundays()->at('16:00');

        $schedule->command('reports:generateReports "reports:generateMerchantSalesInventoryReport" monthly')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->endOfMonth()->isToday();
        });

        // Fabspy MidValley
        $schedule->command('reports:generateChannelMerchantPaymentReport --channels=13')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->startOfMonth()->addDays(1)->isToday();
        });

        // Fabspy SOGO
        $schedule->command('reports:generateChannelMerchantPaymentReport --channels=72')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->startOfMonth()->addDays(1)->isToday();
        });

        $schedule->command('reports:generateMerchantPaymentReport --channelType=online')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->startOfMonth()->addDays(23)->isToday();
        });

        $schedule->command('reports:generateReports "reports:generateMerchantInvoiceReport" weekly')
                 ->tuesdays()->at('16:00');

        /*$schedule->command('command:updateTpReportStatus')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->startOfMonth()->addDays(28)->subDays(2)->isToday();
        });*/


        $schedule->command('Lazada:PullOrders Lazada')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        $schedule->command('Lazada:PullOrders LazadaSC')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        $schedule->command('Zalora:PullOrders')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        $schedule->command('ElevenStreet:PullOrders')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        // $schedule->command('Shopee:PullOrders')
                 // ->withoutOverlapping()
                 // ->everyFiveMinutes();

        $schedule->command('ThirdParty:sync')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        $schedule->command('statistics:generateDashboardStats')->daily();

        $schedule->command('syncs:archive')
                 ->saturdays()->at('16:00');

        $schedule->command('command:checkMarketplacesOrder')
                 ->dailyAt('00:00');

        $schedule->command('alert:criticalOrders') // 10am M'sian time
                 ->dailyAt('02:00');

        $schedule->command('alert:salesExpiry') // 10am M'sian time
                 ->dailyAt('02:00');

        $schedule->command('alert:failedOrders')
                 ->twiceDaily(2, 7);

        $schedule->command('sync:CreateLivePrice') // 11:55pm M'sian time
                 ->dailyAt('15:55');
    }
}
