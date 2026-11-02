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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action', 100);
            $table->string('auditable_type', 255)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('correlation_id', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_user_id');
            $table->index('action');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('correlation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
