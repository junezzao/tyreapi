<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Bican\Roles\Models\Permission;
use Bican\Roles\Models\Role;

class CreateCategoryPermissions extends Migration
{
    protected $permissions = array(
                        [
                          'name' => 'View Category',
                          'slug' => 'view.category',
                          'description' => 'View category',
                        ],
                        [
                          'name' => 'Create Category',
                          'slug' => 'create.category',
                          'description' => 'Create new category',
                        ],
                        [
                          'name' => 'Edit Category',
                          'slug' => 'edit.category',
                          'description' => 'Edit category',
                        ],
                        [
                          'name' => 'Delete Category',
                          'slug' => 'delete.category',
                          'description' => 'Delete category',
                        ],
                    );
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->permissions as $permission) {
            $p = Permission::create($permission);
            $roles = Role::whereIn('name', ['Super Administrator', 'Administrator', 'Finance'])->get();

            foreach ($roles as $role) {
                $role->attachPermission($p->id);
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
        foreach ($this->permissions as $permission) {
            Permission::where('slug', $permission['slug'])->delete();
        }
    }
}
