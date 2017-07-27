<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Generate a random string for password.
     *
     * @param  int $length
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
}
