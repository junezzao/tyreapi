<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToThirdPartyReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_report', function ($table) {
            $table->string('net_payout_currency')->default('MYR')->after('net_payout');
            $table->string('tp_payout_ref')->nullable()->after('payment_date');
            $table->decimal('merchant_payout_amount')->default(0.00)->after('status');
            $table->string('merchant_payout_currency')->default('MYR')->after('merchant_payout_amount');
            $table->string('merchant_payout_status')->nullable()->after('merchant_payout_currency');
            $table->string('hw_payout_bank')->nullable()->after('merchant_payout_status');
            $table->date('merchant_payout_date')->nullable()->after('hw_payout_bank');
            $table->string('merchant_payout_ref')->nullable()->after('merchant_payout_date');
            $table->string('merchant_bank')->nullable()->after('merchant_payout_ref');
            $table->string('merchant_payout_method')->nullable()->after('merchant_bank');
            $table->string('merchant_invoice_no')->nullable()->after('merchant_payout_method');
            $table->string('created_by')->after('merchant_invoice_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_report', function ($table) {
            $table->dropColumn([
                'merchant_payout_amount', 
                'merchant_payout_status', 
                'hw_payout_bank',
                'merchant_payout_date',
                'merchant_payout_ref',
                'merchant_bank',
                'merchant_payout_method',
                'merchant_invoice_no',
                'created_by'
            ]);
        });
    }
}
