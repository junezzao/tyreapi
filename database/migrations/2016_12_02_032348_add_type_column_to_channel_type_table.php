<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;

class AddTypeColumnToChannelTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $newChannelType = new ChannelType;
        // $newChannelType->id = 15;
        $newChannelType->name = "Shopee";
        $newChannelType->status = "Active";
        $newChannelType->third_party = 0;
        $newChannelType->controller = "NonIntegratedController";
        $newChannelType->site = 'MY';
        $newChannelType->manual_order = 1;

        $newChannelType->save();

        Schema::table('channel_types', function (Blueprint $table) {
            $table->string('type')->after('manual_order')->default('');
        });

        $salesChannelTypes = ['Offline Store', 'Shopify', 'Lelong', 'Lazada', 'Zalora', '11Street', 'LazadaSC', 'Storefront Vendor', 'RubberNeck', 'Shopee'];
        $distributionChannelTypes = ['Distribution Center', 'Warehouse'];
        $deprecatedChannelTypes = ['Online Store', 'Marketplace', 'Consignment Counter', 'B2B'];

        $channelTypes = ChannelType::all();

        foreach ($channelTypes as $channelType) {
            $type = '';

            if (in_array($channelType->name, $salesChannelTypes)) {
                $type = 'Sales';
            }
            else if (in_array($channelType->name, $distributionChannelTypes)) {
                $type = 'Distribution';
            }
            else if (in_array($channelType->name, $deprecatedChannelTypes)) {
                $type = 'Deprecated';
            }

            if (!empty($type)) {
                $channelType->type = $type;
                $channelType->save();
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
        $channelType = ChannelType::where('name', '=', 'Shopee')->first();

        if (!is_null($channelType)) {
            $channelType->forceDelete();
        }

        if (Schema::hasColumn('channel_types', 'type'))
        {
            Schema::table('channel_types', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

    }
}
