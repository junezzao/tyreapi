<?php
namespace App\Services;

use Mail;

class Mailer
{
    protected $test;
    protected $email;
    protected $hubwireEmail;

    public function __construct()
    {
        $this->test = (env('APP_ENV') == 'production') ? '' : '[UAT Testing] ';
        $this->email = 'techies@hubwire.com';
        $this->hubwireEmail = '/^[^0-9][_a-z0-9-]+(\.[_a-z0-9-]+)*@hubwire.com$/';
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

    public function scheduledReport($atts)
    {
        Mail::send('reports::emails.scheduled_reports', $atts, function ($message) use ($atts) {
            $message->from(env('MAIL_FROM_ADDRESS', 'technology@hubwire.com'), env('MAIL_FROM_NAME', 'Hubwire Arc Entreprise'));
            if(env('APP_ENV') == 'production')
                $message->to($atts['email']['to'])->subject($this->test.$atts['subject']);
            else
                $message->to($this->email)->subject($this->test.$atts['subject']);
        });
    }

    public function scheduledTaxInvoice($atts)
    {
        Mail::send('reports::emails.scheduled_tax_invoice', $atts, function ($message) use ($atts) {
            $message->from(env('MAIL_FROM_ADDRESS', 'technology@hubwire.com'), env('MAIL_FROM_NAME', 'Hubwire Arc Entreprise'));
            if(env('APP_ENV') == 'production')
                $message->to($atts['email'])->subject($this->test.$atts['subject']);
            else
                $message->to($this->email)->subject($this->test.$atts['subject']);
        });
    }

    public function attentionRequiredNote($atts)
    {
        Mail::send('emails.order_note_notification', $atts, function($message) use ($atts) {
            $message->to( ( env('APP_ENV') == 'production' ? 'ops.marketplace@hubwire.com' : $this->email ) )->subject($this->test.$atts['title']);
        });
    }

    public function criticalOrders($atts)
    {
        Mail::send('emails.critical_order_notification', $atts, function($message) use ($atts) {
            if(env('APP_ENV') == 'production')
            {
                $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.$atts['title']);
                if(!empty($atts['cc'])) $message->cc($atts['cc']);
            }
            else
                $message->to($this->email)->subject($this->test.$atts['title']);

        });
    }

    public function marketplaceMissingOrder($atts)
    {
        Mail::send('emails.marketplace_missing_order', $atts, function($message) use ($atts) {
            if(env('APP_ENV') == 'production')
            {
                $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.$atts['title']);
                if(!empty($atts['cc'])) $message->cc($atts['cc']);
            }
            else
                $message->to($this->email)->subject($this->test.$atts['title']);

        });
    }

    public function thirdPartySyncError($atts)
    {
        Mail::send('emails.third_party_sync_error', array('data'=>$atts), function ($message) use ($atts) {
            $message->from(env('MAIL_FROM_ADDRESS', 'technology@hubwire.com'), env('MAIL_FROM_NAME', 'Hubwire Arc Entreprise'));
            if(env('APP_ENV') == 'production')
                $message->to($atts['email']['to'])->cc($atts['email']['cc'])->subject($this->test.(isset($atts['subject']) ? $atts['subject']:'Error Email with Undefined Subject'));
            else
                $message->to($this->email)->subject($this->test.(isset($atts['subject']) ? $atts['subject']:'Error Email with Undefined Subject'));
        });
    }

    public function failedOrdersNotification($atts)
    {
        Mail::send('emails.failed_orders_notification', $atts, function($message) use ($atts) {
            $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.$atts['title']);
            if(!empty($atts['cc'])) $message->cc($atts['cc']);
        });
    }

    public function salesPeriodExpireNotification($atts)
    {
        Mail::send('emails.sales_period_notification', $atts, function($message) use ($atts) {
            if(env('APP_ENV') == 'production')
            {
                $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.$atts['title']);
                if(!empty($atts['cc'])) $message->cc($atts['cc']);
            }
            else
                $message->to($this->email)->subject($this->test.$atts['title']);
                // $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.$atts['title']);
        });
    }

    public function qtyCheckNotification($atts)
    {
        Mail::send('emails.qty_check_notification', $atts, function($message) use ($atts) {
            $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.' Quantity Check Result for '.$atts['channel']);
            if(!empty($atts['cc'])) $message->cc($atts['cc']);
        });
    }

    public function WebHookFailedNotification($atts)
    {
        Mail::send('emails.webhook_notification', $atts, function($message) use ($atts) {
            $message->to( !empty($atts['to']) ? $atts['to'] : $this->email  )->subject($this->test.' '.$atts['title']);
            if(!empty($atts['cc'])) $message->cc($atts['cc']);
        });
    }

}
