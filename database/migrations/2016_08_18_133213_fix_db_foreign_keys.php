<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixDbForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function($t) {
            DB::statement("ALTER TABLE `orders` CHANGE COLUMN `member_id` `member_id` INT(11) UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE `orders` CHANGE COLUMN `channel_id` `channel_id` INT(11) UNSIGNED NOT NULL");
        });

        Schema::disableForeignKeyConstraints();
        
        // orders
        Schema::table('orders', function ($table) {
            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('channel_id')->references('id')->on('channels');
        });

        // order_notes
        Schema::table('order_notes', function ($table) {
            $table->dropForeign('sales_notes_sale_id_foreign');
            $table->foreign('order_id')->references('id')->on('orders');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('members', function($t) {
            DB::statement("ALTER TABLE `members` CHANGE COLUMN `member_id` `member_id` INT(11) NOT NULL");
            DB::statement("ALTER TABLE `members` CHANGE COLUMN `channel_id` `channel_id` INT(11) NOT NULL");
        });

        Schema::table('orders', function ($table) {
            $table->dropForeign('member_id')->references('id')->on('members');
            $table->dropForeign('channel_id')->references('id')->on('channels');
        });

        // order_notes
        Schema::table('order_notes', function ($table) {
            $table->foreign('sales_notes_sale_id_foreign');
            $table->dropForeign('order_id')->references('id')->on('orders');
        });
        Schema::enableForeignKeyConstraints();
    }
}
