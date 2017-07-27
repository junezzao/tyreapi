<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyFailedOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('failed_orders', function ($table) {
            $table->renameColumn('order_id', 'tp_order_id');
        });
        Schema::table('failed_orders', function ($table) {
            $table->text('error')->change();
            $table->tinyInteger('status')->after('error');
            $table->integer('order_id')->after('channel_id')->nullable();
            $table->dateTime('tp_order_date')->after('status')->nullable();
            $table->integer('user_id')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('failed_orders', function ($table) {
            $table->dropColumn(['status', 'order_id', 'tp_order_date', 'user_id']);
            $table->renameColumn('tp_order_id', 'order_id');
            $table->string('error', 255)->change();
        });
    }
}
