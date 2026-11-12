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
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('client_contact_person')->nullable()->after('client_name');
            $table->json('company_details')->nullable()->after('client_gstin');
            $table->json('project_overview')->nullable()->after('title');
            $table->json('scope_of_work')->nullable()->after('notes');
            $table->json('tech_stack')->nullable()->after('scope_of_work');
            $table->json('timeline_phases')->nullable()->after('tech_stack');
            $table->json('payment_milestones')->nullable()->after('timeline_phases');
            $table->json('support_terms')->nullable()->after('payment_milestones');
            $table->json('signatory_details')->nullable()->after('support_terms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'client_contact_person',
                'company_details',
                'project_overview',
                'scope_of_work',
                'tech_stack',
                'timeline_phases',
                'payment_milestones',
                'support_terms',
                'signatory_details',
            ]);
        });
    }
};
