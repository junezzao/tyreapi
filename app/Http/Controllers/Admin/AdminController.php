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

    public function removeNonAlphaNum($str){
        return preg_replace('/\s+/', '', preg_replace('/[^A-Za-z0-9 ]/','_',$str));
    }
}
