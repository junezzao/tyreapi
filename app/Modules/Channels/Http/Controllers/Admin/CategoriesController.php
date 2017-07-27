<?php
namespace App\Modules\Channels\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use App\Modules\ThirdParty\Http\Controllers\ThirdPartyController;
use LucaDegasperi\OAuth2Server\Authorizer;
use App\Models\Admin\ChannelType;
use App\Models\Admin\Channel;
use App\Models\Admin\ProductThirdPartyCategory;
use App\Http\Requests;
use Illuminate\Http\Request;
use Input;
use DB;
use Log;
use Activity;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use Storage;

class CategoriesController extends AdminController
{
    protected $authorizer;

    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('oauth');
        $this->authorizer = $authorizer;
    }

    public function getOutdatedCategoriesProducts($channel_type_id)
    {
        $products = ChannelType::select('merchants.id as merchant_id', 'merchants.name as merchant_name', 'products.id as product_id', 'products.name as product_name', 'products.brand as brand_name', 'tpcat.cat_id as category_id', 'tpcat.cat_name as category_name')
                    ->join('channels', 'channels.channel_type_id', '=', 'channel_types.id')
                    ->join('channel_sku', 'channel_sku.channel_id', '=', 'channels.id')
                    ->join('products', 'channel_sku.product_id', '=', 'products.id')
                    ->join('merchants', 'products.merchant_id', '=', 'merchants.id')
		            ->join('product_third_party_categories as tpcat', 'channel_sku.product_id', '=', 'tpcat.product_id')
                    ->where('channel_types.id', $channel_type_id)
                    ->groupBy('channel_sku.product_id')
                    ->get();

        return response()->json($products);
    }

    public function updateThirdPartyProductCategories($channel_type_id)
    {
        $channel_type = ChannelType::where('id', $channel_type_id)->firstOrFail();

        $tpc = new ThirdPartyController;
        $response = $tpc->getCategories($channel_type_id);

        if(!empty($response['categories'])){
            // Write to file
            $categories = $response['categories'];
            $fileName = $channel_type->name.'-'.$channel_type->site;
            $file = fopen(config_path()."/categories/{$fileName}.php", "w") or die("Unable to open file!");
            $line = "<?php\nreturn array(\n\"Select ". ucfirst($channel_type->name) ." Category\"=>0,\n";
            fwrite($file, $line);
            foreach ($categories as $cat_id => $cat_name)
            {
                $cat_name = urldecode(str_replace('%C2%A0', '', urlencode($cat_name)));
                $line = "\"". $cat_name ."\"=>". $cat_id .",\n";
                fwrite($file, $line);
            }
            $line = ");";
            fwrite($file, $line);

            Activity::log('Third Party Categories for '. $channel_type->name .' have been updated.', $this->authorizer->getResourceOwnerId());
            
            /**
            ** Upload config to Amazon S3
            **/
            $uploadedfile = config_path()."/categories/{$fileName}.php";
            $s3path = 'categories/'.$fileName.'.php';
            $s3upload = Storage::disk('s3')->put($s3path, file_get_contents($uploadedfile));

            /*
                Send Notification to CRON Server about the event
            */
            try {
                $client = new \GuzzleHttp\Client();
                $client->post(env('CRON_URL','http://cron.hubwire.com').'/events/listener', [
                    'form_params' => [ 
                        'id' => null,
                        'event' => 'categories/updated'
                        ]
                    ]
                );
            }
            catch(\Exception $e)
            {
                \Log::error('Broadcast event failed with message: '.$e->getMessage());
            }

            return response()->json($response);
        }
        
        return response()->json($response);
    }

    public function getActiveCategories($channel_type_id)
    {
        $categories = ProductThirdPartyCategory::select('product_third_party_categories.cat_id')
                        ->join('channels', 'product_third_party_categories.channel_id', '=', 'channels.id')
                        ->where('channels.channel_type_id', $channel_type_id)
                        ->where('channels.status', 'Active')
                        ->groupBy('product_third_party_categories.cat_id')
                        ->get();

        return response()->json($categories);
    }

    public function remapThirdPartyProductCategories($channel_type_id)
    {
        $data = Input::get('data', []);

        DB::beginTransaction();
        foreach($data as $row) {
            $products = ProductThirdPartyCategory::select('product_third_party_categories.id')
                        ->leftjoin('channels', 'product_third_party_categories.channel_id', '=', 'channels.id')
                        ->where('channels.channel_type_id', $channel_type_id)
                        ->where('product_third_party_categories.cat_id', $row['from'])
                        ->get();

            foreach($products as $product) {
                $product->cat_id = $row['to'];
                $product->cat_name = $row['cat_name'];
                $product->save();
            }
        }

        DB::commit();

        return response()->json(array('success'=>true));
    }

    // get categories from config file
    public function getCategories(Request $request) {
        $channel_type = $request->input('channel_type_name', '');
        $data = [];
        if (in_array($channel_type, config('globals.third_party_categories_applicable'))) {
            $data = config('categories.'.$channel_type.'-MY');
        }

        return response()->json($data);
    }
}
