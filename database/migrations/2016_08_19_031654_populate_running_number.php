<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PopulateRunningNumber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE running_number CHANGE current_no current_no INT(5) UNSIGNED ZEROFILL NOT NULL');

        $companies = DB::table('issuing_companies')->get();
        foreach($companies as $company)
        {
            //invoice no
            $order_invoice = DB::table('order_invoice')->where('tax_invoice_no','like',$company->prefix.'%')->orderBy('id','desc')->take(1)->first();
            if(!is_null($order_invoice)){
                $last_invoice_no = explode('-',$order_invoice->tax_invoice_no);
                $last_invoice_no = intval($last_invoice_no[1]);
            }
            else {
                $last_invoice_no = 0;
            }
        
            DB::statement("insert into running_number (type,prefix,current_no) values('tax_invoice','".$company->prefix."',$last_invoice_no) ");
            
            //credit note
            $credit_note = DB::table('order_credit_note')->where('credit_note_no','like','CN-'.$company->prefix.'%')->orderBy('id','desc')->take(1)->first();
            if(!is_null($credit_note)){
                $last_credit_note_no = explode('-',$credit_note->credit_note_no);
                $last_credit_note_no = intval($last_credit_note_no[2]);
            }
            else
            {
                $last_credit_note_no = 0;
            }
            
            DB::statement("insert into running_number (type,prefix,current_no) values('credit_note','".$company->prefix."',$last_credit_note_no) ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("truncate running_number");
    }
}
