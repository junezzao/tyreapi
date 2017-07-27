<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;
use Bican\Roles\Models\Permission;

class InsertInitialRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'superadministrator',
                'description' => 'Super Administrator',
                'level' => 100
            ],
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Administrator - previously hw_admins',
                'level' => 10
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Finance and reporting',
                'level' => 9
            ],
            [
                'name' => 'Account Executive',
                'slug' => 'accountexec',
                'description' => 'Account Executive',
                'level' => 4
            ],
            [
                'name' => 'Partners',
                'slug' => 'partner',
                'description' => 'Partners - previously, partners',
                'level' => 3
            ],
            [
                'name' => 'Client Admin',
                'slug' => 'clientadmin',
                'description' => 'Client Admin - Administrator role for a client',
                'level' => 2
            ],
            [
                'name' => 'Client User',
                'slug' => 'clientuser',
                'description' => 'Client Admin - previously, client_admins',
                'level' => 1
            ]
        ];
        // Assign all users with a role
        foreach ($roles as $role) {
            $r = Role::create($role);
            User::whereCategory($role['name'])
                ->chunk(1000, function ($users) use ($r) {
                    foreach ($users as $user) {
                        $user->attachRole($r);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('roles')->truncate();
        DB::table('role_user')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
