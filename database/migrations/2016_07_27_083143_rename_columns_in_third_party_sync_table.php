<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameColumnsInThirdPartySyncTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_sync', function ($table) {
            $table->renameColumn('sync_id', 'id');
            $table->renameColumn('channel_type', 'channel_type_id');
            $table->renameColumn('client_id', 'merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_sync', function ($table) {
            $table->renameColumn('id', 'sync_id');
            $table->renameColumn('channel_type_id', 'channel_type');
            $table->renameColumn('merchant_id', 'client_id');
        });
    }
}
