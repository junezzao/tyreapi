<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\DashboardStatisticsRepository;
use Vinkla\Pusher\Facades\Pusher;

class ResetDashboardCounters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistics:resetDashboardCounters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset dashboard live counters';

    protected $pusher;
    protected $dashboardRepo;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->dashboardRepo = new DashboardStatisticsRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        Pusher::trigger('order-statistics', 'orders-count-update', $this->dashboardRepo->countOrdersAndOrderItems());
        Pusher::trigger('order-statistics', 'returned-count-update', $this->dashboardRepo->countReturnedItems());
        Pusher::trigger('order-statistics', 'cancelled-count-update', $this->dashboardRepo->countCancelledItems());
    }
}
