<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MoveMinGuaranteeToContract extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contracts', function ($table) {
            $table->boolean('min_guarantee')->default(0)->after('guarantee');
        });

        Schema::table('channel_contracts', function ($table) {
            $table->boolean('min_guarantee')->default(0)->after('guarantee');
        });

        Schema::table('contract_rules', function ($table) {
            $table->dropColumn('min_guarantee');
        });

        Schema::table('channel_contract_rules', function ($table) {
            $table->dropColumn('min_guarantee');
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
            $table->dropColumn('min_guarantee');
        });

        Schema::table('channel_contracts', function ($table) {
            $table->dropColumn('min_guarantee');
        });

        Schema::table('contract_rules', function ($table) {
            $table->boolean('min_guarantee')->after('products');
        });

        Schema::table('channel_contract_rules', function ($table) {
            $table->boolean('min_guarantee')->after('products');
        });
    }
}
