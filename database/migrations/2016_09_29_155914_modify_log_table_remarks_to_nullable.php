<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyLogTableRemarksToNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quantity_log', function ($table) {
            $table->text('remarks')->nullable()->change();          
        });

        Schema::table('quantity_log_app', function ($table) {
            $table->text('remarks')->nullable()->change();          
        });

        Schema::table('inventory_stock_cache', function ($table) {
            $table->text('remarks')->nullable()->change();          
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quantity_log', function ($table) {
            $table->text('remarks')->nullable(false)->change();          
        });

        Schema::table('quantity_log_app', function ($table) {
            $table->text('remarks')->nullable(false)->change();          
        });

        Schema::table('inventory_stock_cache', function ($table) {
            $table->text('remarks')->nullable(false)->change();          
        });
    }
}
