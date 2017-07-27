<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateContractRuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contract_rules', function ($table) {
            $table->boolean('fixed_charge')->default(0)->after('min_guarantee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contract_rules', function ($table) {
            $table->dropColumn('fixed_charge');
        });
    }
}
