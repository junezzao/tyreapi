<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Role;
use App\Models\User;
use Bican\Roles\Models\Permission;

class InsertUserRole extends Migration
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
                'name' => 'User',
                'slug' => 'user',
                'description' => 'User',
                'level' => 1
            ],
        ];
        // Assign all users with a role
        foreach ($roles as $role) {
            $r = Role::create($role);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}
