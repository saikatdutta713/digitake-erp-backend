<?php

namespace App\Modules\Project\Services;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class QuotationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List quotations with search, filters, and pipeline metrics.
     *
     * @return array<string, mixed>
     */
    public function listQuotations(Request $request): array
    {
        $query = Quotation::with(['project', 'createdBy', 'items', 'convertedToProject'])
            ->latest('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_company', 'like', "%{$search}%");
            });
        }

        $records = $query->get();

        $all = Quotation::all();
        $totalSent = $all->whereIn('status', ['sent', 'accepted', 'converted'])->sum('grand_total');
        $totalAccepted = $all->whereIn('status', ['accepted', 'converted'])->sum('grand_total');

        $stats = [
            'total_quotes' => $all->count(),
            'draft_count' => $all->where('status', 'draft')->count(),
            'sent_count' => $all->where('status', 'sent')->count(),
            'accepted_count' => $all->whereIn('status', ['accepted', 'converted'])->count(),
            'total_quoted_pipeline' => round($totalSent, 2),
            'total_won_pipeline' => round($totalAccepted, 2),
            'conversion_rate' => $all->count() > 0 ? round(($all->whereIn('status', ['accepted', 'converted'])->count() / $all->count()) * 100, 1) : 0,
        ];

        return [
            'stats' => $stats,
            'count' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Create quotation with dynamic items.
     *
     * @param  array<string, mixed>  $data
     */
    public function createQuotation(array $data, User $actor): Quotation
    {
        $data['quotation_number'] = $data['quotation_number'] ?? Quotation::generateNumber();
        $data['created_by_user_id'] = $actor->id;
        $data['issue_date'] = $data['issue_date'] ?? Carbon::today()->toDateString();
        $data['valid_until'] = $data['valid_until'] ?? Carbon::today()->addDays(30)->toDateString();
        $data['status'] = $data['status'] ?? 'draft';
        $data['currency'] = $data['currency'] ?? 'INR';
        $data['tax_type'] = $data['tax_type'] ?? 'gst';
        $data['is_inter_state'] = (bool) ($data['is_inter_state'] ?? false);

        $items = $data['items'] ?? [];
        unset($data['items']);

        $quotation = Quotation::create($data);

        foreach ($items as $item) {
            $quotation->items()->create([
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'hsn_sac_code' => $item['hsn_sac_code'] ?? '998314',
                'quantity' => $item['quantity'] ?? 1,
                'unit' => $item['unit'] ?? 'units',
                'unit_price' => $item['unit_price'] ?? 0,
                'discount_rate' => $item['discount_rate'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 18.00,
            ]);
        }

        $quotation->recalculateTotals();

        $this->auditService->log(
            'quotation.created',
            $quotation,
            null,
            $quotation->toArray(),
            $actor
        );

        return $quotation->fresh(['project', 'createdBy', 'items']);
    }

    /**
     * Update quotation details and line items.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateQuotation(Quotation $quotation, array $data, User $actor): Quotation
    {
        $old = $quotation->toArray();

        $items = $data['items'] ?? null;
        unset($data['items']);

        $quotation->update($data);

        if ($items !== null) {
            $quotation->items()->delete();
            foreach ($items as $item) {
                $quotation->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'hsn_sac_code' => $item['hsn_sac_code'] ?? '998314',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? 'units',
                    'unit_price' => $item['unit_price'] ?? 0,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'tax_rate' => $item['tax_rate'] ?? 18.00,
                ]);
            }
        }

        $quotation->recalculateTotals();

        $this->auditService->log(
            'quotation.updated',
            $quotation,
            $old,
            $quotation->toArray(),
            $actor
        );

        return $quotation->fresh(['project', 'createdBy', 'items', 'convertedToProject']);
    }

    /**
     * 1-Click Conversion: Convert accepted quotation directly into a Project!
     */
    public function convertToProject(Quotation $quotation, User $actor): Project
    {
        $project = Project::create([
            'project_code' => Project::generateCode(),
            'name' => $quotation->title,
            'description' => "Created from Quotation #{$quotation->quotation_number}: ".($quotation->notes ?? ''),
            'client_name' => $quotation->client_name,
            'client_email' => $quotation->client_email,
            'client_phone' => $quotation->client_phone,
            'client_company' => $quotation->client_company,
            'client_gstin' => $quotation->client_gstin,
            'client_address' => $quotation->client_address,
            'created_by_user_id' => $actor->id,
            'status' => 'contract_signed',
            'priority' => 'high',
            'billing_type' => 'fixed_cost',
            'currency' => $quotation->currency,
            'estimated_budget' => $quotation->grand_total,
            'progress_percentage' => 0,
            'start_date' => Carbon::today()->toDateString(),
            'deadline' => Carbon::today()->addMonths(2)->toDateString(),
        ]);

        $quotation->update([
            'status' => 'converted',
            'converted_to_project_id' => $project->id,
            'project_id' => $project->id,
        ]);

        $this->auditService->log(
            'quotation.converted_to_project',
            $quotation,
            null,
            ['project_id' => $project->id, 'project_code' => $project->project_code],
            $actor
        );

        return $project->load(['department', 'projectManager', 'createdBy', 'milestones', 'tasks']);
    }

    /**
     * 1-Click Conversion: Convert quotation directly into a GST / Non-GST Invoice!
     */
    public function convertToInvoice(Quotation $quotation, User $actor, ?string $invoiceType = null): Invoice
    {
        $type = $invoiceType ?? ($quotation->tax_type === 'gst' ? 'gst_tax_invoice' : 'non_gst_invoice');

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateNumber($type),
            'invoice_type' => $type,
            'project_id' => $quotation->project_id,
            'quotation_id' => $quotation->id,
            'created_by_user_id' => $actor->id,
            'client_name' => $quotation->client_name,
            'client_company' => $quotation->client_company,
            'client_email' => $quotation->client_email,
            'client_phone' => $quotation->client_phone,
            'client_address' => $quotation->client_address,
            'client_gstin' => $quotation->client_gstin,
            'place_of_supply' => $quotation->place_of_supply ?? 'Maharashtra (27)',
            'is_inter_state' => $quotation->is_inter_state,
            'issue_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(15)->toDateString(),
            'status' => 'issued',
            'currency' => $quotation->currency,
            'subtotal' => $quotation->subtotal,
            'discount_total' => $quotation->discount_total,
            'taxable_amount' => $quotation->taxable_amount,
            'cgst_total' => $quotation->cgst_total,
            'sgst_total' => $quotation->sgst_total,
            'igst_total' => $quotation->igst_total,
            'grand_total' => $quotation->grand_total,
            'paid_amount' => 0,
            'balance_amount' => $quotation->grand_total,
            'payment_terms' => 'Payment due within 15 days of invoice date via NEFT/RTGS or UPI.',
            'notes' => "Invoice generated against Quotation #{$quotation->quotation_number}",
        ]);

        foreach ($quotation->items as $item) {
            $invoice->items()->create([
                'item_name' => $item->item_name,
                'description' => $item->description,
                'hsn_sac_code' => $item->hsn_sac_code,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_rate' => $item->discount_rate,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'taxable_value' => $item->taxable_value,
                'cgst_amount' => $item->cgst_amount,
                'sgst_amount' => $item->sgst_amount,
                'igst_amount' => $item->igst_amount,
                'total_amount' => $item->total_amount,
            ]);
        }

        $quotation->update(['status' => 'accepted']);

        $this->auditService->log(
            'quotation.converted_to_invoice',
            $quotation,
            null,
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number],
            $actor
        );

        return $invoice->load(['project', 'quotation', 'createdBy', 'items']);
    }

    /**
     * Delete quotation.
     */
    public function deleteQuotation(Quotation $quotation, User $actor): bool
    {
        $old = $quotation->toArray();
        $quotation->delete();

        $this->auditService->log('quotation.deleted', $quotation, $old, null, $actor);

        return true;
    }
}
