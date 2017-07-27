<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\ChannelType;

class AssignThirdPartyValueToChannelType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ChannelType::whereIn('name', ['Shopify', 'Lelong', 'Lazada', 'Zalora', '11Street'])->update(['third_party' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ChannelType::whereIn('name', ['Shopify', 'Lelong', 'Lazada', 'Zalora', '11Street'])->update(['third_party' => 0]);
    }
}
