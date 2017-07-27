<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyChannelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('channels')->where('channel_type', '=', 0)->update(['channel_type' => 12]);
        DB::statement("ALTER TABLE channels MODIFY COLUMN channel_type INT(10) UNSIGNED");

        Schema::table('channels', function ($table) {
            $table->renameColumn('channel_id', 'id');
            $table->renameColumn('channel_name', 'name');
            $table->renameColumn('channel_address', 'address');
            $table->renameColumn('channel_web', 'website_url');
            $table->renameColumn('channel_type', 'channel_type_id');
            //$table->renameColumn('client_id', 'merchant_id');
            $table->renameColumn('channel_currency', 'currency');
            $table->renameColumn('channel_timezone', 'timezone');
            $table->renameColumn('inactive', 'status');

            $table->integer('merchant_id')->after('client_id')->default(0);
            $table->dropColumn('reserve_enable');
            $table->dropColumn('conversion_rate');
            $table->dropColumn('channel_template');

            $table->foreign('channel_type_id')->references('id')->on('channel_types')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE channels MODIFY COLUMN client_id INT(10) UNSIGNED AFTER channel_type_id");
        DB::statement("ALTER TABLE channels MODIFY COLUMN merchant_id INT(10) UNSIGNED AFTER client_id");
        DB::statement("ALTER TABLE channels MODIFY COLUMN currency VARCHAR(30) AFTER merchant_id");
        DB::statement("ALTER TABLE channels MODIFY COLUMN timezone VARCHAR(50) AFTER currency");
        DB::statement("ALTER TABLE channels MODIFY COLUMN status VARCHAR(255) AFTER timezone");

        DB::table('channels')->where('status', '=', 0)->update(['status' => 'Active']);
        DB::table('channels')->where('status', '=', 1)->update(['status' => 'Inactive']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function ($table) {
            $table->dropForeign('channels_channel_type_id_foreign');

            $table->renameColumn('id', 'channel_id');
            $table->renameColumn('name', 'channel_name');
            $table->renameColumn('address', 'channel_address');
            $table->renameColumn('website_url', 'channel_web');
            $table->renameColumn('channel_type_id', 'channel_type');
            $table->dropColumn('merchant_id');
            $table->renameColumn('currency', 'channel_currency');
            $table->renameColumn('timezone', 'channel_timezone');
            $table->renameColumn('status', 'inactive');

            $table->tinyInteger('reserve_enable')->default(0);
            $table->float('conversion_rate')->default(1);
            $table->integer('channel_template')->default(0);
        });

        DB::statement("ALTER TABLE channels MODIFY COLUMN inactive TINYINT(1) DEFAULT 0");
    }
}
