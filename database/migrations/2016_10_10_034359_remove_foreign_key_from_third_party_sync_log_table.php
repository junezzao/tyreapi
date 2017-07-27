<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveForeignKeyFromThirdPartySyncLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('third_party_sync_log', function ($table) {
            $table->dropForeign('third_party_sync_log_sync_id_foreign');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('third_party_sync_log', function ($table) {
            $table->foreign('sync_id')->references('id')->on('third_party_sync')->onDelete('cascade');
        });
    }
}
