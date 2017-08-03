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
        Commands\UpdateUserSubscriptionPlan::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('command:updateUserSubscriptionPlan')
                 ->dailyAt('16:00');

        /* $schedule->command('inspire')
                 ->hourly();

        $schedule->command('statistics:resetDashboardCounters')
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

        $schedule->command('reports:generateChannelMerchantPaymentReport --channels=13')->dailyAt('16:00')->when(function () {
            return \Carbon\Carbon::now()->startOfMonth()->addDays(1)->isToday();
        });

        $schedule->command('Lazada:PullOrders Lazada')
                 ->withoutOverlapping()
                 ->everyFiveMinutes();

        $schedule->command('statistics:generateDashboardStats')->daily();

        $schedule->command('alert:failedOrders')
                 ->twiceDaily(2, 7); */
    }
}
