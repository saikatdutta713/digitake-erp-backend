<?php

namespace App\Modules\Project\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Modules\Project\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(
        protected QuotationService $quotationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->quotationService->listQuotations($request);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_contact_person' => ['nullable', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'company_details' => ['nullable', 'array'],
            'title' => ['required', 'string', 'max:255'],
            'project_overview' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,rejected,expired,converted'],
            'currency' => ['nullable', 'string', 'max:10'],
            'tax_type' => ['nullable', 'string', 'in:gst,non_gst'],
            'is_inter_state' => ['nullable', 'boolean'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'terms_and_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'array'],
            'tech_stack' => ['nullable', 'array'],
            'timeline_phases' => ['nullable', 'array'],
            'payment_milestones' => ['nullable', 'array'],
            'support_terms' => ['nullable', 'array'],
            'signatory_details' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hsn_sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $quotation = $this->quotationService->createQuotation($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Quotation created successfully.',
            'data' => $quotation,
        ], 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        $quotation->load(['project', 'createdBy', 'items', 'convertedToProject']);

        return response()->json([
            'status' => 'success',
            'data' => $quotation,
        ]);
    }

    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_contact_person' => ['nullable', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'company_details' => ['nullable', 'array'],
            'title' => ['nullable', 'string', 'max:255'],
            'project_overview' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,rejected,expired,converted'],
            'currency' => ['nullable', 'string', 'max:10'],
            'tax_type' => ['nullable', 'string', 'in:gst,non_gst'],
            'is_inter_state' => ['nullable', 'boolean'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'terms_and_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'scope_of_work' => ['nullable', 'array'],
            'tech_stack' => ['nullable', 'array'],
            'timeline_phases' => ['nullable', 'array'],
            'payment_milestones' => ['nullable', 'array'],
            'support_terms' => ['nullable', 'array'],
            'signatory_details' => ['nullable', 'array'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hsn_sac_code' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $updated = $this->quotationService->updateQuotation($quotation, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Quotation updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Quotation $quotation): JsonResponse
    {
        $this->quotationService->deleteQuotation($quotation, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Quotation deleted successfully.',
        ]);
    }

    public function convertToProject(Request $request, Quotation $quotation): JsonResponse
    {
        $project = $this->quotationService->convertToProject($quotation, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Quotation #{$quotation->quotation_number} converted to Project #{$project->project_code}.",
            'data' => $project,
        ]);
    }

    public function convertToInvoice(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type' => ['nullable', 'string', 'in:gst_tax_invoice,non_gst_invoice'],
        ]);

        $invoice = $this->quotationService->convertToInvoice($quotation, $request->user(), $validated['invoice_type'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => "Invoice #{$invoice->invoice_number} created from Quotation.",
            'data' => $invoice,
        ]);
    }
}
