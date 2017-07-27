<?php namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SKU;
use App\Services\Mailer;

class AdminController extends Controller
{
		/**
     * Generate a random string for password.
     *
     * @param  int  $length
     * @return string
     */
    protected function generateRandomString()
    {
        $alphabets = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $numbers = '23456789';
        $randomString = '';

        for ($i = 0; $i < 12; $i++) {
            $randomString .= $alphabets[rand(0, strlen($alphabets) - 1)];
        }
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $numbers[rand(0, strlen($numbers) - 1)];
        }
        $randomString = str_shuffle($randomString);

        return $randomString;
    }

    public function HWSKU($sku_id){
        $sku = SKU::with('combinations','product')->findOrFail($sku_id);
        $option_arr = array();
        foreach($sku->combinations as $sku_option){
            $option_arr[strtolower(trim($sku_option->option_name))] = array('value'=>trim(strip_tags($sku_option->option_value)),'id'=>$sku_option->option_id);
        }
        $colours = config('colours');
        $colour_desc = array_keys($colours);
        // Map 'Colour' option value to the colour code table $colours
        $colour = in_array( strtolower($option_arr['colour']['value']), $colour_desc)? $colours[strtolower($option_arr['colour']['value'])]:strtoupper(trim(substr($option_arr['colour']['value'],0,3)));
        // $product = Product::find($sku->product_id);
        $sku->hubwire_sku = $sku_id.$sku->product->brand.sprintf("%06d", $sku->product_id).'-'.$this->removeNonAlphaNum($colour).'-'.$this->removeNonAlphaNum(strtoupper($option_arr['size']['value']));
        $sku->save();
        return $sku->hubwire_sku;
    }

    public function removeNonAlphaNum($str){
        return preg_replace('/\s+/', '', preg_replace('/[^A-Za-z0-9 ]/','_',$str));
    }

    public function ErrorAlert($email_data){
        // if(env('APP_ENV') != 'production') return;

        $emails = array(
                    'to' => array('yuki@hubwire.com'), 
                    'cc' => array(
                        'mahadhir@hubwire.com',
                        'jun@hubwire.com'
                    )
                );

        $email_data['email'] = $emails;

        $mailer = new Mailer;
        $mailer->thirdPartySyncError($email_data);
    }

}
