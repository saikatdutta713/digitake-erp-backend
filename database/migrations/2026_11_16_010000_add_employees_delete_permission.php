<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create employees.delete permission under Employee module
        $permission = Permission::firstOrCreate(
            ['name' => 'employees.delete', 'guard_name' => 'web'],
            ['module_group' => 'Employee']
        );

        // 2. Grant to Super Admin, Admin, and HR Manager roles
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permission);
        }

        $hrManager = Role::where('name', 'HR Manager')->first();
        if ($hrManager) {
            $hrManager->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'employees.delete')->delete();
    }
};

