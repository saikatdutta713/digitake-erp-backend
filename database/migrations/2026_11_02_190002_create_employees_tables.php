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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('official_email', 255)->unique();
            $table->string('personal_email', 255)->nullable();
            $table->string('phone_number', 50);
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_joining');
            $table->date('date_of_exit')->nullable();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('designation_id')->constrained('designations')->restrictOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 50)->default('full_time');
            $table->string('employment_status', 50)->default('active');
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('employment_status');
            $table->index('reporting_manager_id');
        });

        // Add foreign key constraint to departments.manager_id now that employees exists
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::create('employee_bank_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->string('bank_name', 255);
            $table->string('account_number', 100);
            $table->string('routing_code', 100); // IFSC / SWIFT / Routing Code
            $table->string('branch_name', 255)->nullable();
            $table->string('account_type', 50)->default('savings');
            $table->timestamps();
        });

        Schema::create('employee_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('old_status', 50);
            $table->string('new_status', 50);
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('document_type', 100); // Resume, ID Proof, Contract, Offer Letter
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_status_history');
        Schema::dropIfExists('employee_bank_details');
        Schema::dropIfExists('employees');
    }
};
