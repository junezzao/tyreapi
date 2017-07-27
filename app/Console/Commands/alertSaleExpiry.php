<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin\ChannelSKU;
use App\Models\Admin\Merchant;
use App\Models\User;
use Carbon\Carbon;
use App\Services\Mailer;
use DB;


class alertSaleExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:salesExpiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sales Period Expiry Alert';

    protected $emails = array(
        'to' => array(
                'geraldine@hubwire.com',
                'waiwai@hubwire.com',
                'licco@hubwire.com',
                'likhang@hubwire.com',
                'viknes@hubwire.com',
                'nicholas@hubwire.com',
                ),
        'cc' => array('rachel@hubwire.com','hehui@hubwire.com','jun@hubwire.com')
        );

    protected $mailer;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Mailer $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = Carbon::now('Asia/Kuala_Lumpur');
        $tomorrow = $now->copy()->addDay();
        // $now = Carbon::createFromFormat('Y-m-d','2017-03-04','Asia/Kuala_Lumpur'); // test date
        // $tomorrow = Carbon::createFromFormat('Y-m-d','2017-04-30','Asia/Kuala_Lumpur'); // test date

        // \Log::info($now->format('Y-m-d'));
        // \Log::info($tomorrow->format('Y-m-d'));
                
        $data = $this->emails;
        $data['title'] = 'Sales Period Notification';
        $data['today'] = $now->format('Y-m-d');
        $data['tomorrow'] = $tomorrow->format('Y-m-d');

        $starting = array();
        $expiring = array();

        // About to start 
        $about_to_start = ChannelSKU::setEagerLoads([])
                                ->select(DB::raw('channel_sku.*,products.brand_id,brands.name as brand_name'))
                                ->leftJoin('products','products.id','=','channel_sku.product_id')
                                ->leftJoin('merchants','merchants.id','=','products.merchant_id')
                                ->leftJoin('brands','brands.id','=','products.brand_id')
                                ->leftJoin('channels','channels.id','=','channel_sku.channel_id')
                                ->leftJoin('channel_types','channel_types.id','=','channels.channel_type_id')
                                ->whereHas('channel',function($query) {
                                    $query->where('status','=','Active');
                                })
                                // ->whereHas('merchant',function($query) {
                                //    $query->where('status','=','Active');
                                // })
                                ->whereRaw('promo_start_date = ? ',[$tomorrow->format('Y-m-d')])
                                ->whereRaw('brands.deleted_at IS NULL')
                                ->whereRaw('products.deleted_at IS NULL')
                                ->whereRaw('channel_sku.deleted_at IS NULL')
                                ->where('brands.active',1)
                                ->where('products.active',1)
                                ->where('channel_sku_active',1)
                                ->where('channel_types.type','=','Sales')
                                ->where('merchants.status','Active')
                                ->orderBy('channel_id')->get();

        // Group it by brand
        $brands_to_start = $about_to_start->groupBy('brand_id');

        // Count product by brand
        foreach($brands_to_start as $brand)
        {
            $starting[] = (object) ['brand_name'=> $brand[0]->brand_name, 'products' => $brand->groupBy('product_id')->count(), 'skus'=> $brand->groupBy('sku_id')->count()];
        }
         
        // About to expire        
        $about_to_expired = ChannelSKU::setEagerLoads([])
                                ->select(DB::raw('channel_sku.*,products.brand_id,brands.name as brand_name'))
                                ->leftJoin('products','products.id','=','channel_sku.product_id')
                                ->leftJoin('merchants','merchants.id','=','products.merchant_id')
                                ->leftJoin('brands','brands.id','=','products.brand_id')
                                ->leftJoin('channels','channels.id','=','channel_sku.channel_id')
                                ->leftJoin('channel_types','channel_types.id','=','channels.channel_type_id')
                                ->whereHas('channel',function($query) {
                                    $query->where('status','=','Active');
                                })
                                // ->whereHas('merchant',function($query) {
                                //    $query->where('status','=','Active');
                                // })
                                ->whereRaw('promo_end_date = ?',[$now->format('Y-m-d')])
                                // ->whereRaw('promo_end_date >= DATE_ADD(?, INTERVAL 14 HOUR)',[$now->format('Y-m-d')])
                                // ->whereRaw('promo_end_date < DATE_ADD(?, INTERVAL 28 HOUR)',[$now->format('Y-m-d')])
                                ->whereRaw('brands.deleted_at IS NULL')
                                ->whereRaw('products.deleted_at IS NULL')
                                ->whereRaw('channel_sku.deleted_at IS NULL')
                                ->where('brands.active',1)
                                ->where('products.active',1)
                                ->where('channel_sku_active',1)
                                ->where('channel_types.type','=','Sales')
                                ->where('merchants.status','Active')
                                ->orderBy('channel_id')
                                ->get();

        // Group it by brand
        $brands_to_expire = $about_to_expired->groupBy('brand_id');

        // Count product by brand
        foreach($brands_to_expire as $brand)
        {
            $expiring[] = (object) ['brand_name'=> $brand[0]->brand_name, 'products' => $brand->groupBy('product_id')->count(), 'skus'=> $brand->groupBy('sku_id')->count()];
        }

        // \Log::info(print_r($starting, true));
        // \Log::info(print_r($expiring, true));


        $data['starting'] = $starting;
        $data['expiring'] = $expiring;

        if(!empty($data['starting']) || !empty($data['expiring']))
        {
            $this->mailer->salesPeriodExpireNotification($data);
        }
    }
}
