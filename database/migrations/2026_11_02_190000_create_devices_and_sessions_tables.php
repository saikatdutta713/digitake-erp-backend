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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid', 100)->unique();
            $table->string('device_name', 255);
            $table->string('device_type', 50)->default('personal'); // personal, shared
            $table->string('platform', 50)->default('windows');
            $table->string('os_version', 100)->nullable();
            $table->string('app_version', 50);
            $table->string('status', 50)->default('active'); // active, revoked, pending_approval
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('device_uuid');
        });

        Schema::create('device_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sanctum_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('ip_address', 45);
            $table->string('session_status', 50)->default('active'); // active, logged_out, expired, revoked_by_admin
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();

            $table->index(['device_id', 'user_id', 'session_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
        Schema::dropIfExists('devices');
    }
};
