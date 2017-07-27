<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Mailer;
use App\Repositories\FailedOrderRepository as FailedOrderRepo;

class FailedOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:failedOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send an email notifying failed orders.';

    protected $emails = array('to' => array('ops.marketplace@hubwire.com'),
                              'cc' =>array(
                                    'rachel@hubwire.com',
                                    'yuki@hubwire.com',
                                    'hehui@hubwire.com',
                                    'jun@hubwire.com'
                                )
                            );

    protected $failedOrderRepo;

    protected $mailer;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FailedOrderRepo $failedOrderRepo, Mailer $mailer)
    {
        parent::__construct();
        $this->failedOrderRepo = $failedOrderRepo;
        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try{
            $data = $this->emails;
            $data['channels'] = $this->failedOrderRepo->emailData();
            $data['title'] = 'RSO Attention: Failed Pulled Order Notification.';
            if(count($data['channels']) > 0)
                $this->mailer->failedOrdersNotification($data);
        }catch(\Exception $e){
            \Log::info($e->getMessage());
        }
    }
}
