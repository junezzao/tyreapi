<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MoveStorefrontsOnlyColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $storefronts = DB::table('channels')
                        ->join('channel_details', 'channels.id', '=', 'channel_details.channel_id')
                        ->where('channels.channel_type_id', '=', 1)
                        ->select('channels.*', 'channels.id as channel_id', 'channel_details.*')
                        ->get();

        foreach ($storefronts as $store) {
            DB::table('storefronts')->insert([
                    'channel_id'            => $store->channel_id,
                    'ipay_merchant_code'    => $store->ipay_merchant_code,
                    'ipay_key'              => $store->ipay_key,
                    'paypal_email'          => $store->paypal_email,
                    'paypal_token'          => $store->paypal_token,
                    'facebook_app_id'       => $store->facebook_app_id,
                    'facebook_app_secret'   => $store->facebook_app_secret,
                    'google_analytics'      => $store->google_analytics,
                    'channel_title'         => $store->channel_title,
                    'channel_description'   => $store->channel_description,
                    'channel_keyword'       => $store->channel_keyword,
                    'channel_template'      => $store->channel_template
                ]);
        }

        Schema::table('channel_details', function ($table) {
            $table->dropColumn('ipay_merchant_code');
            $table->dropColumn('ipay_key');
            $table->dropColumn('paypal_email');
            $table->dropColumn('paypal_token');
            $table->dropColumn('facebook_app_id');
            $table->dropColumn('facebook_app_secret');
            $table->dropColumn('google_analytics');
            $table->dropColumn('channel_title');
            $table->dropColumn('channel_description');
            $table->dropColumn('channel_keyword');
            $table->dropColumn('channel_template');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $storefronts = DB::table('channels')
                        ->where('channels.channel_type_id', '=', 1)
                        ->select('id')
                        ->get();

        foreach ($storefronts as $store) {
            DB::table('storefronts')->where('channel_id', '=', $store->id)->delete();
        }

        Schema::table('channel_details', function ($table) {
            $table->string('ipay_merchant_code', 60);
            $table->string('ipay_key', 60);
            $table->string('paypal_email', 60);
            $table->string('paypal_token', 60);
            $table->string('facebook_app_id', 100);
            $table->string('facebook_app_secret', 100);
            $table->text('google_analytics')->nullable();
            $table->string('channel_title', 100);
            $table->string('channel_description', 100);
            $table->string('channel_keyword', 100);
            $table->integer('channel_template');
        });
    }
}
