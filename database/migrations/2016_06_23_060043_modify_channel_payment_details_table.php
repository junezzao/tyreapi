<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyChannelPaymentDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('channel_payment_details', 'channel_details');

        Schema::table('channel_details', function ($table) {
            $table->renameColumn('detail_id', 'id');
        });

        DB::statement("ALTER TABLE channel_details MODIFY COLUMN channel_id INT(10) UNSIGNED AFTER id");
        DB::statement("ALTER TABLE channel_details MODIFY COLUMN created_at TIMESTAMP AFTER extra_info");
        DB::statement("ALTER TABLE channel_details MODIFY COLUMN updated_at TIMESTAMP AFTER created_at");
        DB::statement("ALTER TABLE channel_details MODIFY COLUMN deleted_at TIMESTAMP NULL AFTER updated_at");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('channel_details', 'channel_payment_details');

        Schema::table('channel_payment_details', function ($table) {
            $table->renameColumn('id', 'detail_id');
        });
    }
}
