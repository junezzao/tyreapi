<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameOrdersRelatedTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // order_notes table
        // rename table sale_notes to order_notes, update columns names
        if (!Schema::hasTable('order_notes')) {
            Schema::rename('sales_notes', 'order_notes');
            Schema::table('order_notes', function ($table) {
                $table->renameColumn('sale_id', 'order_id');
                $table->renameColumn('admin_id', 'user_id');
            });
        }
        
        // order_status_log table
        // rename sale_status_log table and column name change
        if (!Schema::hasTable('order_status_log')) {
            Schema::rename('sale_status_log', 'order_status_log');
            Schema::table('order_status_log', function ($table) {
                $table->renameColumn('log_id', 'id');
                $table->renameColumn('admin_id', 'user_id');
                $table->renameColumn('sale_id', 'order_id');
            });
        }
    
        // reserved quantity
        // rename sold_quantity table and column name change
        if (!Schema::hasTable('reserved_quantities')) {
            Schema::rename('sold_quantity', 'reserved_quantities');
            Schema::table('reserved_quantities', function ($table) {
                $table->renameColumn('sold_id', 'id');
            });
        }

        // reserved_quantities_log
        // rename sold_quantity_log table and column name change
        if (!Schema::hasTable('reserved_quantities_log')) {
            Schema::rename('sold_quantity_log', 'reserved_quantities_log');
            Schema::table('reserved_quantities_log', function ($table) {
                $table->renameColumn('log_id', 'id');
                $table->integer('order_id')->after('quantity_new');
            });
        }

        // order_notes
        DB::table('order_notes')->chunk(1000, function ($notes) {
            foreach ($notes as $note) {
                DB::table('order_notes')->where('note_type', '=', '')->update([
                  'note_type' => 'General'
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('order_notes', 'sales_notes');
        Schema::table('sales_notes', function ($table) {
            $table->renameColumn('order_id', 'sale_id');
            $table->renameColumn('user_id', 'admin_id');
        });
        Schema::rename('order_status_log', 'sale_status_log');
        Schema::table('sale_status_log', function ($table) {
            $table->renameColumn('id', 'log_id');
            $table->renameColumn('user_id', 'admin_id');
            $table->renameColumn('order_id', 'sale_id');
        });
        Schema::rename('reserved_quantity_log', 'sold_quantities_log');
        Schema::table('sold_quantities_log', function ($table) {
            $table->renameColumn('id', 'log_id');
            $table->dropColumn('order_id');
        });
        Schema::rename('reserved_quantity', 'sold_quantities');
        Schema::table('sold_quantities', function ($table) {
            $table->renameColumn('id', 'sold_id');
        });
    }
}
