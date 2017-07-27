<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;

class AddLazadascChannelType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $lazada = ChannelType::where('name', '=', 'Lazada')->first();

        $channelType = new ChannelType;
        $channelType->id = 13;
        $channelType->name = "LazadaSC";
        $channelType->status = "Active";
        $channelType->fields = $lazada->fields;
        $channelType->third_party = 1;
        $channelType->controller = "LazadaScController";
        $channelType->site = $lazada->site;
        $channelType->manual_order = $lazada->manual_order;

        $channelType->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ChannelType::where('name', '=', 'LazadaSC')->first()->forceDelete();
    }
}
