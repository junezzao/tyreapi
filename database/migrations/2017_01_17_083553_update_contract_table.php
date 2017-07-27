<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateContractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contracts', function ($table) {
            $table->dropColumn('status');
            $table->date('end_date')->nullable()->after('brand_id');
            $table->date('start_date')->nullable()->after('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contracts', function ($table) {
            $table->dropColumn(['end_date', 'start_date']);
            $table->string('status');
        });
    }
}
