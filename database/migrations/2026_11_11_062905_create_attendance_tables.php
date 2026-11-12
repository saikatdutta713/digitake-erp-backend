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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->dateTime('clock_in_at');
            $table->dateTime('clock_out_at')->nullable();
            $table->unsignedInteger('total_duration_minutes')->default(0);
            $table->unsignedInteger('active_minutes')->default(0);
            $table->unsignedInteger('idle_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('productivity_score', 5, 2)->default(100.00);
            $table->string('modality', 30)->default('office'); // office, wfh, hybrid
            $table->string('status', 30)->default('present'); // present, late, half_day, absent, on_leave, on_duty
            $table->string('device_uuid')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->foreignId('override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
            $table->index('modality');
        });

        Schema::create('attendance_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('interval_start');
            $table->dateTime('interval_end');
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedInteger('break_seconds')->default(0);
            $table->string('state', 30)->default('active'); // active, idle, break
            $table->string('break_type', 50)->nullable(); // lunch, tea, meeting, personal
            $table->string('source', 50)->default('desktop_agent');
            $table->timestamps();

            $table->index(['attendance_id', 'interval_start']);
            $table->index(['user_id', 'interval_start']);
        });

        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('standard_start_time', 10)->default('09:30');
            $table->string('standard_end_time', 10)->default('18:30');
            $table->unsignedInteger('late_grace_minutes')->default(15);
            $table->unsignedInteger('half_day_threshold_minutes')->default(240);
            $table->unsignedInteger('full_day_threshold_minutes')->default(480);
            $table->unsignedInteger('idle_timeout_seconds')->default(180);
            $table->json('allowed_modalities')->nullable();
            $table->json('office_ips')->nullable();
            $table->string('auto_clock_out_at', 10)->nullable()->default('22:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_activities');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_policies');
    }
};
