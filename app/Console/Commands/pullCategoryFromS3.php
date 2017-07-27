<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Storage;

class pullCategoryFromS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'category:pullFromS3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Marketplace Categories from Amazon S3';

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
        \Log::info($this->description);
        $files = Storage::disk('s3')->allFiles('categories');
        foreach($files as $file)
        {
            file_put_contents(config_path().'/'.$file, fopen(Storage::disk('s3')->url($file), 'r'));
        }
    }
}
