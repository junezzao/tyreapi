<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Storage;
use AWS;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use App\Models\Admin\Order;
use Carbon\Carbon;

class moveDocumentsOnS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 's3:moveDocuments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move tax invoice documents in S3 to the correct folder';

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
        // Storage::disk('s3')->makeDirectory('tmp');
        $files = Storage::disk('s3')->allFiles('documents');
        foreach($files as $file)
        {
            // check if matched 'documents/{order_id}/{filename}'
            if(substr_count($file, '/') !== 2) continue; 
            
            $this->info('Found: '.$file);
            $keys = explode('/', $file);
            
            $order_id = $keys[1];
            
            $order  = Order::find($order_id);
            $filename = 'tax_invoice_' . $order_id . '_' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y-m-d')  . '.pdf';
            
            $s3path = 'documents/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('Y') . '/' . Carbon::createFromFormat('Y-m-d H:i:s', $order->created_at)->format('m') . '/' . $order_id . '/' . $filename;
            try {
                // move to new destination
                Storage::disk('s3')->move($file,$s3path);
            }
            catch(\Exception $e)
            {
                $this->error($e->getMessage());
            }

            $this->info(Storage::disk('s3')->url($s3path));

        }
        $this->info('Finish!');
        // delete the old folder
        // $deleted = Storage::disk('s3')->deleteDirectory('tmp');
        // $this->info(var_export($deleted));
        
    }
}
