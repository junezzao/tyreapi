<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMgStartFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contracts', function ($table) {
            $table->boolean('start_charge_mg')->default(0)->after('min_guarantee');
        });

        Schema::table('channel_contracts', function ($table) {
            $table->boolean('start_charge_mg')->default(0)->after('min_guarantee');
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
            $table->dropColumn('start_charge_mg');
        });

        Schema::table('channel_contracts', function ($table) {
            $table->dropColumn('start_charge_mg');
        });
    }
}
