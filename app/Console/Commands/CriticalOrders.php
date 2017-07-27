<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\Contracts\OrderRepository as OrderRepository;
use App\Services\Mailer;

class CriticalOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:criticalOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert Critical Order Level 1,2,3 & 4 and Send Email';

    protected $emails = array('to' => array('ops.marketplace@hubwire.com'),
                              'cc' =>array(
                                    'geraldine@hubwire.com',
                                    'gary@hubwire.com',
                                    'jedidiah@hubwire.com',
                                    'mark@hubwire.com',
                                    'alex@hubwire.com',
                                    'rachel@hubwire.com',
                                    'yuki@hubwire.com',
                                    'hehui@hubwire.com',
                                    'jun@hubwire.com'
                                ));
    protected $orderRepo;
    protected $mailer;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(OrderRepository $order, Mailer $mailer)
    {
        parent::__construct();
        $this->orderRepo = $order;
        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // try{
            $data = $this->emails;
            $levels = $this->orderRepo->countLevels();
            unset($levels[1]); // remove level 1 orders from emails
            $tlevels = array_filter($levels);
            if(count($tlevels)>0){
                $data['levels'] = $levels;
                $data['title'] = 'Alert! Critical Orders';
                $this->mailer->criticalOrders($data);
            }

        // }
        // catch(\Exception $e)
        // {
            // $this->error($e->getMessage() );
        // }
    }
}
