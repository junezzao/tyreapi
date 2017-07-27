<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateAndAssignPermissionsForChannelTypeAndCategory extends Migration
{
    protected $permissions = array([
                          'name' => 'Create channel type',
                          'slug' => 'create.channeltype',
                          'description' => '',
                       ],
                       [
                          'name' => 'View channel type',
                          'slug' => 'view.channeltype',
                          'description' => '',
                       ],
                       [
                          'name' => 'Edit channel type',
                          'slug' => 'edit.channeltype',
                          'description' => '',
                       ],
                       [
                          'name' => 'Delete channel type',
                          'slug' => 'delete.channeltype',
                          'description' => '',
                       ],
                       [
                          'name' => 'Manage category',
                          'slug' => 'manage.category',
                          'description' => '',
                       ]);

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //remove define.channel
        $permission = Permission::where('slug', '=', 'define.channel')->first();
        $roles = Role::whereIn('slug', ['superadministrator', 'administrator', 'finance', 'accountexec'])->get();

        foreach ($roles as $role) {
            $role->detachPermission($permission);
        }
        
        $permission->delete();

        //assign new permissions
        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);

            $slugs = ['superadministrator', 'administrator'];

            if ($permission['slug'] == 'view.channeltype') {
                $slugs[] = 'finance';
                $slugs[] = 'accountexec';
            }
            else if ($permission['slug'] == 'manage.category') {
                $slugs[] = 'accountexec';
            }
            else if ($permission['slug'] == 'create.channeltype' || $permission['slug'] == 'edit.channeltype') {
                $slugs[] = 'finance';
            }

            $roles = Role::whereIn('slug', $slugs)->get();

            foreach ($roles as $role) {
                $role->attachPermission($p);
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
        //Re-add define.channel
        $defineChannel = array(
                          'name' => 'Define channel',
                          'slug' => 'define.channel',
                          'description' => 'Create and edit channel type',
                       );

        $p = Permission::create($defineChannel);
        $roles = Role::whereIn('slug', ['superadministrator', 'administrator', 'finance'])->get();

        foreach ($roles as $role) {
            $role->attachPermission($p);
        }

        //remove permissions
        $permissions = Permission::whereIn('slug', array_pluck($this->permissions, 'slug'))->get();

        foreach ($permissions as $permission) {
            $slugs = ['superadministrator', 'administrator'];

            if ($permission->slug == 'view.channeltype') {
                $slugs[] = 'finance';
                $slugs[] = 'accountexec';
            }
            else if ($permission->slug == 'manage.category') {
                $slugs[] = 'accountexec';
            }
            else if ($permission->slug == 'create.channeltype' || $permission->slug == 'edit.channeltype') {
                $slugs[] = 'finance';
            }

            $roles = Role::whereIn('slug', $slugs)->get();

            foreach ($roles as $role) {
                $role->detachPermission($permission);
            }

            $permission->delete();
        }

    }
}
