<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ConvertExtraInfoColumnDataToJsonOnChannelDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channel_details = DB::table('channel_details')->select('id', 'extra_info')->get();

        foreach ($channel_details as $detail) {
            if (!is_null($detail->extra_info)) {
                $extra_info = json_encode(unserialize($detail->extra_info));
                DB::table('channel_details')->where('id', '=', $detail->id)->update(['extra_info' => $extra_info]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $channel_details = DB::table('channel_details')->select('id', 'extra_info')->get();

        foreach ($channel_details as $detail) {
            if (!is_null($detail->extra_info)) {
                $extra_info = serialize(json_decode($detail->extra_info, true));
                DB::table('channel_details')->where('id', '=', $detail->id)->update(['extra_info' => $extra_info]);
            }
        }
    }
}
