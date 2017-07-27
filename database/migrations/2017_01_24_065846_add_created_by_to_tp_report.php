<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCreatedByToTpReport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('third_party_report', 'created_by')) {
            Schema::table('third_party_report', function ($table) {
                $table->integer('created_by')->change();
            });
        }else{
            Schema::table('third_party_report', function ($table) {
                $table->integer('created_by')->after('merchant_invoice_no');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
