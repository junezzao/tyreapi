<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Channel;
use App\Models\Admin\Client;

class AppendClientNameToChannelName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channels = Channel::all();
        foreach ($channels as $channel) {
            $client = Client::find($channel->client_id);
            if (isset($client->client_name)) {
                $channel->name = '['.$client->client_name.'] '.$channel->name;
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $channel->save();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $channels = Channel::all();
        foreach ($channels as $channel) {
            $clientName = preg_match('/(\[.*?\])/', $channel->name, $matches);
            if (isset($matches[0])) {
                $channel->name = trim(str_replace($matches[0], '', $channel->name));
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $channel->save();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
