<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Channel;
use App\Models\Admin\Purchase;

class AssignChannelToPurchaseBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Purchase::chunk(1000, function ($batches) {
            foreach ($batches as $batch) {
                $channel = Channel::where('client_id', $batch->client_id)->where('channel_type_id', 12)->first();
                $batch->channel_id = $channel->id;
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                $batch->save();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
        //
    }
}
