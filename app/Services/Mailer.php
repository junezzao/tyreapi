<?php
namespace App\Services;

use Mail;

class Mailer
{
    protected $test;

    public function __construct()
    {
        $this->test = (env('APP_ENV') == 'production') ? '' : '[UAT Testing] ';
    }

    public function accountVerification($atts)
    {
        Mail::send('emails.user_verification', $atts, function ($message) use ($atts) {
            $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $message->to($atts['email'], $atts['recipientName'])->subject($this->test.'Welcome to '.env('APP_NAME'));
        });
    }

    public function resetPassword($atts)
    {
    	Mail::send('emails.password_reset', $atts, function ($message) use ($atts) {
            $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            $message->to($atts['email'], $atts['recipientName'])->subject($this->test.env('APP_NAME').' Password Reset');
        });
    }

}
