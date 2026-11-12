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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applied_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acting_delegate_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leave_type', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 4, 1)->default(1.0);
            $table->string('half_day_type', 20)->default('none');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->boolean('is_auto_approved')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
