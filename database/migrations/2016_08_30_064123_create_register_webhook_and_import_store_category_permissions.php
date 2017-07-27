<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateRegisterWebhookAndImportStoreCategoryPermissions extends Migration
{
    protected $permissions = array([
                          'name' => 'Register webhook',
                          'slug' => 'register.webhook',
                          'description' => 'Register webhook permission for Shopify',
                       ],
                       [
                          'name' => 'Import store category',
                          'slug' => 'import.storecategory',
                          'description' => 'Import store category permission for Lelong',
                       ]);

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $roles = Role::whereIn('slug', ['superadministrator', 'administrator', 'operationsexec', 'accountexec', 'channelmanager'])->get();

        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);            

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
        $permissions = Permission::whereIn('slug', array_pluck($this->permissions, 'slug'))->get();
        $roles = Role::whereIn('slug', ['superadministrator', 'administrator', 'operationsexec', 'accountexec', 'channelmanager'])->get();

        foreach ($permissions as $permission) {
            foreach ($roles as $role) {
                $role->detachPermission($permission);
            }

            $permission->delete();
        }
    }
}
