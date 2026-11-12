<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectQuotationInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@digitake.local',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->department = Department::create([
            'name' => 'Software Engineering',
            'code' => 'SE',
            'is_active' => true,
        ]);
    }

    public function test_can_create_and_list_projects_with_stages(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $createRes = $this->postJson('/api/v1/projects', [
            'name' => 'Enterprise ERP Deployment',
            'client_name' => 'Acme Corporation',
            'client_company' => 'Acme Global Ltd',
            'client_email' => 'contact@acme.com',
            'client_gstin' => '27ABCDE1234F1Z5',
            'department_id' => $this->department->id,
            'status' => 'planning',
            'priority' => 'high',
            'billing_type' => 'fixed_cost',
            'estimated_budget' => 250000.00,
            'start_date' => now()->toDateString(),
            'deadline' => now()->addMonths(3)->toDateString(),
            'milestones' => [
                ['title' => 'Sprint 1 - Database Architecture', 'amount' => 75000.00],
                ['title' => 'Sprint 2 - Frontend UI Build', 'amount' => 75000.00],
                ['title' => 'Sprint 3 - UAT & Production Handover', 'amount' => 100000.00],
            ],
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Enterprise ERP Deployment')
            ->assertJsonPath('data.status', 'planning');

        $this->assertDatabaseHas('projects', [
            'name' => 'Enterprise ERP Deployment',
            'client_name' => 'Acme Corporation',
        ]);

        $this->assertDatabaseCount('project_milestones', 3);

        $listRes = $this->getJson('/api/v1/projects');
        $listRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.count', 1);
    }

    public function test_can_build_quotation_with_gst_tax_breakdown(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/v1/quotations', [
            'title' => 'Cloud Migration & ERP Customization Quote',
            'client_name' => 'Apex Retailers',
            'client_company' => 'Apex Retail Private Limited',
            'client_email' => 'billing@apexretail.in',
            'client_gstin' => '27AAACA1234A1Z1',
            'place_of_supply' => 'Maharashtra (27)',
            'tax_type' => 'gst',
            'is_inter_state' => false, // intra-state: CGST 9% + SGST 9%
            'items' => [
                [
                    'item_name' => 'Cloud Architecture Setup',
                    'hsn_sac_code' => '998314',
                    'quantity' => 1,
                    'unit' => 'project',
                    'unit_price' => 100000.00,
                    'discount_rate' => 0,
                    'tax_rate' => 18.00,
                ],
                [
                    'item_name' => 'Dedicated Consulting Hours',
                    'hsn_sac_code' => '998313',
                    'quantity' => 50,
                    'unit' => 'hours',
                    'unit_price' => 1000.00,
                    'discount_rate' => 10.00, // 10% off: 50,000 - 5,000 = 45,000
                    'tax_rate' => 18.00,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Subtotal = 100k + 50k = 150k
        // Discount = 5k
        // Taxable = 145k
        // 18% GST (CGST 9% = 13,050, SGST 9% = 13,050) = 26,100
        // Grand total = 171,100
        $quotation = Quotation::latest()->first();
        $this->assertEquals(150000.00, $quotation->subtotal);
        $this->assertEquals(5000.00, $quotation->discount_total);
        $this->assertEquals(145000.00, $quotation->taxable_amount);
        $this->assertEquals(13050.00, $quotation->cgst_total);
        $this->assertEquals(13050.00, $quotation->sgst_total);
        $this->assertEquals(171100.00, $quotation->grand_total);
    }

    public function test_can_convert_quotation_to_project_and_invoice(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Create Quote
        $quoteRes = $this->postJson('/api/v1/quotations', [
            'title' => 'Mobile Field Staff Agent App',
            'client_name' => 'Zenith Logistics',
            'client_company' => 'Zenith Global',
            'client_email' => 'ops@zenith.com',
            'tax_type' => 'gst',
            'is_inter_state' => true, // IGST 18%
            'items' => [
                [
                    'item_name' => 'Android Native App',
                    'quantity' => 1,
                    'unit_price' => 200000.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ]);
        $quotationId = $quoteRes->json('data.id');

        // 2. Convert to Project
        $projRes = $this->postJson("/api/v1/quotations/{$quotationId}/convert-to-project");
        $projRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'contract_signed');

        $this->assertDatabaseHas('quotations', [
            'id' => $quotationId,
            'status' => 'converted',
        ]);

        // 3. Convert to Invoice
        $invRes = $this->postJson("/api/v1/quotations/{$quotationId}/convert-to-invoice", [
            'invoice_type' => 'gst_tax_invoice',
        ]);
        $invRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.invoice_type', 'gst_tax_invoice');

        $invoiceId = $invRes->json('data.id');

        // 4. Record Partial & Full Payment
        $payRes = $this->postJson("/api/v1/invoices/{$invoiceId}/payment", [
            'amount' => 100000.00,
            'note' => 'NEFT Advance received transaction #TXN987654',
        ]);

        $payRes->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_paid');
        $this->assertEquals(100000.00, (float) $payRes->json('data.paid_amount'));
    }

    public function test_can_create_and_fetch_complete_nine_section_quotation_proposal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $payload = [
            'title' => 'Turnkey Enterprise Software & Cloud Platform Proposal',
            'client_name' => 'Apex Technologies Private Limited',
            'client_contact_person' => 'Rahul Sharma',
            'client_company' => 'Apex Group',
            'client_email' => 'rahul@apexgroup.in',
            'client_phone' => '+91 98200 12345',
            'client_address' => 'Tech Boulevard, Powai, Mumbai, MH 400076',
            'client_gstin' => '27AAACA1234A1Z1',
            'place_of_supply' => 'Maharashtra (27)',
            'tax_type' => 'gst',
            'is_inter_state' => false,
            'issue_date' => '2026-11-11',
            'valid_until' => '2026-12-11',
            'project_overview' => [
                'project_name' => 'Custom Enterprise ERP & Cloud Platform',
                'project_type' => 'Web Application / ERP',
                'description' => 'Comprehensive architecture, design, and deployment of an integrated enterprise management system.',
            ],
            'scope_of_work' => [
                ['sl_no' => '01', 'service' => 'UI/UX Design', 'description' => 'Interface wireframing and design system.'],
                ['sl_no' => '02', 'service' => 'Frontend Development', 'description' => 'Responsive frontend implementation.'],
                ['sl_no' => '03', 'service' => 'Backend Development', 'description' => 'Server-side business logic and APIs.'],
            ],
            'tech_stack' => [
                ['component' => 'Frontend', 'technology' => 'React / TypeScript'],
                ['component' => 'Backend', 'technology' => 'Laravel 11 / PHP 8.3'],
            ],
            'timeline_phases' => [
                ['phase' => 'Phase 1', 'deliverables' => 'Requirement Analysis', 'duration' => '7 Days'],
                ['phase' => 'Phase 2', 'deliverables' => 'Development', 'duration' => '20 Days'],
            ],
            'payment_milestones' => [
                ['milestone' => 'Kickoff', 'percentage' => 40, 'amount' => 40000.00],
                ['milestone' => 'UAT', 'percentage' => 40, 'amount' => 40000.00],
                ['milestone' => 'Handover', 'percentage' => 20, 'amount' => 20000.00],
            ],
            'support_terms' => [
                ['service' => 'Bug Fixing', 'coverage' => '30 Days', 'charges' => 'Included Free'],
            ],
            'signatory_details' => [
                'company_signatory_name' => 'Authorized Signatory',
                'company_signatory_designation' => 'Director',
            ],
            'items' => [
                [
                    'item_name' => 'Custom ERP Architecture & Modules',
                    'hsn_sac_code' => '998314',
                    'quantity' => 1,
                    'unit' => 'project',
                    'unit_price' => 100000.00,
                    'tax_rate' => 18.00,
                ],
            ],
        ];

        $res = $this->postJson('/api/v1/quotations', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.client_contact_person', 'Rahul Sharma')
            ->assertJsonPath('data.project_overview.project_type', 'Web Application / ERP')
            ->assertJsonPath('data.scope_of_work.0.service', 'UI/UX Design')
            ->assertJsonPath('data.tech_stack.1.technology', 'Laravel 11 / PHP 8.3')
            ->assertJsonPath('data.payment_milestones.0.milestone', 'Kickoff');

        $quoteId = $res->json('data.id');

        $getRes = $this->getJson("/api/v1/quotations/{$quoteId}");
        $getRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.project_overview.project_name', 'Custom Enterprise ERP & Cloud Platform');
    }
}
