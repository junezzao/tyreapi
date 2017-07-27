<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class InsertAdminOauthClientCredentials extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('oauth_clients')->insert([
            'id' => 'f3d259ddd3ed8ff3843839b',
            'authenticatable_type' => 'HWAdmin',
            'name' => 'HWAdmin',
            'secret' => '4c7f6f8fa93d59c45502c0ae8c4a95b',
            'slug' => 'hwadmin',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        DB::table('oauth_client_endpoints')->insert([
            'client_id' => 'f3d259ddd3ed8ff3843839b',
            'redirect_uri' => config('app.url'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('oauth_clients')->where('id', '=', 'f3d259ddd3ed8ff3843839b')->delete();
        DB::table('oauth_client_endpoints')->where('client_id', '=', 'f3d259ddd3ed8ff3843839b')->delete();
    }
}
