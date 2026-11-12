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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->decimal('days_per_year', 4, 1)->default(12.0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allow_half_day')->default(true);
            $table->decimal('carry_forward_days', 4, 1)->default(0.0);
            $table->string('applicable_gender', 20)->default('all');
            $table->string('color', 20)->default('#10b981');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
