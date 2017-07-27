<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Admin\Channel;

class PopulateDocsToPrintColInChannels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*
         * Set default documents to print for each channel as per requirements:
         * Zalora, order sheet, hw tax invoice, zalora tax invoice
         * [shopify] polo haus -  order sheet, hw tax invoice, return slip
         * [shopify] Fabspy - order sheet, hw tax invoice, return slip
         * All other channels - order sheet, hw tax invoice
         */
        DB::table('channels')->update(['docs_to_print' => '0, 1']);
        // zalora
        Channel::where('channel_type_id', '=', '9')->update(['docs_to_print' => '0, 1, 3']);
        // polo haus and fabspy shopify
        Channel::where('channel_type_id', '=', '6')->where(function ($query) {
            $query->where('name', 'like', '%Polo Haus%')
                  ->orWhere('name', 'like', '%Fabspy%');
        })->update(['docs_to_print' => '0, 1, 2']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Channel::update(['docs_to_print' => '']);
    }
}
