<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChannelTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('channel_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('status');
            $table->string('fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        $channel_types = array(
            1 => 'Online Store',
            2 => 'Marketplace',
            3 => 'Offline Store',
            4 => 'Consignment Counter',
            5 => 'B2B',
            6 => 'Shopify',
            7 => 'Lelong',
            8 => 'Lazada',
            9 => 'Zalora',
            10 => '11Street',
            11 => 'Distribution Center',
            12 => 'Warehouse',
        );

        foreach ($channel_types as $key => $value) {
            DB::table('channel_types')->insert([
                    'name'       => $value,
                    'status'     => 'Active',
                    'created_at' => gmdate("Y-m-d H:i:s"),
                    'updated_at' => gmdate("Y-m-d H:i:s")
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('channel_types');
    }
}
