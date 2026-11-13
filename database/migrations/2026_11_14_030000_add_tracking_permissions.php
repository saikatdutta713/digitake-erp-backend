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
        $permissions = [
            'tracking.view' => 'Tracking',
            'tracking.manage' => 'Tracking',
            'tracking.export' => 'Tracking',
        ];

        foreach ($permissions as $name => $group) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module_group' => $group]
            );
        }

        // Grant to Super Admin, HR Manager, and Department Manager
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(array_keys($permissions));
        }

        $hr = Role::where('name', 'HR Manager')->first();
        if ($hr) {
            $hr->givePermissionTo(['tracking.view', 'tracking.manage', 'tracking.export']);
        }

        $mgr = Role::where('name', 'Department Manager')->first();
        if ($mgr) {
            $mgr->givePermissionTo(['tracking.view', 'tracking.export']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['tracking.view', 'tracking.manage', 'tracking.export'])->delete();
    }
};

