<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;
use App\Models\User;
use Carbon\Carbon;

class CreateOeChannelAdminRoles extends Migration
{
    protected $role = [
                            'name' => 'Operations Executive',
                            'slug' => 'operationsexec',
                            'description' => 'Operations Executive',
                            'level' => 4

                        ];
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // create OE role and attach same permission as AE

        $r = Role::create($this->role);
        
        // get AE role and permission
        $ae = Role::with('permissions')->where('name', 'Account Executive')->first();
        foreach ($ae->permissions as $p) {
            $r->attachPermission($p->id);
        }

        // insert test accounts
        $user_id = DB::table('users')->insertGetId([
             'first_name' => 'Operations Executive',
             'last_name' => 'Test',
             'email' => 'oe@hubwire.com',
             'password' => bcrypt('Hubwire!'),
             'remember_token' => '',
             'contact_no' => '0123456789',
             'address' => '',
             'timezone' => 'Asia/Kuala_Lumpur',
             'currency' => 'MYR',
             'status' => 'Active',
             'old_id' => '',
             'category' => 'Operations Executive',
             'created_at' => Carbon::now('UTC'),
             'updated_at' => Carbon::now('UTC'),
             'deleted_at' => null,
        ]);

        $user = User::find($user_id);
        $user->attachRole($r);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // delete OE role and detach permission
        $oe = Role::where('name', 'Operations Executive')->first();
        $oe->detachAllPermissions();

        $r = Role::with('users')->where('name', 'Operations Executive')->first();
        foreach ($r->users as $user) {
            $user->detachRole($oe);
        }

        DB::table('roles')->where('name', '=', 'Operations Executive')->delete();
    }
}
