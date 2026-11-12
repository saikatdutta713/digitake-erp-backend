<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('role_type', 30)->default('custom')->after('is_system_role');
            $table->foreignId('department_id')->nullable()->after('role_type')->constrained('departments')->nullOnDelete();
            $table->index(['role_type', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['role_type', 'department_id']);
            $table->dropColumn(['role_type', 'department_id']);
        });
    }
};
