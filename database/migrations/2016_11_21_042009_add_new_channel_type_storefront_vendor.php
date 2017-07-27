<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;


class AddNewChannelTypeStorefrontVendor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $channelType = new ChannelType;
        $channelType->name = 'Storefront Vendor';
        $channelType->status = 'Active';
        $channelType->third_party = 1;
        $channelType->controller = "StorefrontVendorController";
        $channelType->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ChannelType::where('name', '=', 'Storefront Vendor')->first()->forceDelete();
    }
}
