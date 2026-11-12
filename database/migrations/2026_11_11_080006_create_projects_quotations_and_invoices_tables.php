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
        // 1. Projects Table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_company')->nullable();
            $table->string('client_gstin', 25)->nullable();
            $table->text('client_address')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 35)->default('planning');
            // Statuses: lead, quotation_sent, contract_signed, planning, in_progress, in_review, completed, invoiced, paid_closed, on_hold, cancelled
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('billing_type', 30)->default('fixed_cost'); // fixed_cost, time_and_materials, retainer, internal
            $table->string('currency', 10)->default('INR');
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->decimal('actual_cost', 14, 2)->default(0);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('client_name');
        });

        // 2. Project Milestones
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 25)->default('pending'); // pending, in_progress, completed
            $table->decimal('amount', 14, 2)->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        // 3. Project Tasks
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('status', 25)->default('todo'); // todo, in_progress, review, done
            $table->decimal('estimated_hours', 6, 2)->default(0);
            $table->decimal('actual_hours', 6, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        // 4. Quotations Table
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 40)->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_name');
            $table->string('client_company')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->text('client_address')->nullable();
            $table->string('client_gstin', 25)->nullable();
            $table->string('title');
            $table->string('status', 30)->default('draft'); // draft, sent, accepted, rejected, expired, converted
            $table->string('currency', 10)->default('INR');
            $table->string('tax_type', 20)->default('gst'); // gst, non_gst
            $table->boolean('is_inter_state')->default(false); // true: IGST, false: CGST + SGST
            $table->string('place_of_supply', 100)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('converted_to_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // 5. Quotation Line Items
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('hsn_sac_code', 20)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('units');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(18.00); // 0, 5, 12, 18, 28
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        // 6. Invoices Table (GST Tax Invoice & Non-GST Commercial Invoices)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->string('invoice_type', 30)->default('gst_tax_invoice'); // gst_tax_invoice, non_gst_invoice
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_name');
            $table->string('client_company')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->text('client_address')->nullable();
            $table->string('client_gstin', 25)->nullable();
            $table->string('company_name')->default('Digitake Solutions Private Limited');
            $table->text('company_address')->nullable();
            $table->string('company_gstin', 25)->nullable()->default('27AADCD1234F1Z5');
            $table->string('company_pan', 20)->nullable()->default('AADCD1234F');
            $table->string('place_of_supply', 100)->nullable()->default('Maharashtra (27)');
            $table->boolean('is_inter_state')->default(false);
            $table->string('bank_name')->nullable()->default('HDFC Bank Ltd');
            $table->string('bank_account_number')->nullable()->default('50200012345678');
            $table->string('bank_ifsc')->nullable()->default('HDFC0001234');
            $table->string('bank_branch')->nullable()->default('Corporate Banking Branch');
            $table->string('bank_upi_id')->nullable()->default('digitake@hdfcbank');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 30)->default('draft'); // draft, issued, partially_paid, paid, overdue, cancelled
            $table->string('currency', 10)->default('INR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->text('payment_terms')->nullable()->default('Payment due within 15 days of invoice date.');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('invoice_type');
        });

        // 7. Invoice Line Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('hsn_sac_code', 20)->nullable()->default('998314');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('units');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(18.00);
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
    }
};
