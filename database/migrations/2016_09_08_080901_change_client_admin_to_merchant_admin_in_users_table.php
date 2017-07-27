<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\User;

class ChangeClientAdminToMerchantAdminInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = User::where('category', 'like', '%Client%')->withTrashed()->get();

        foreach ($users as $user) {
            $user->category = str_replace("Client", "Merchant", $user->category);
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $users = User::where('category', 'like', '%Merchant%')->withTrashed()->get();

        foreach ($users as $user) {
            $user->category = str_replace("Merchant", "Client", $user->category);
            $user->save();
        }
    }
}
