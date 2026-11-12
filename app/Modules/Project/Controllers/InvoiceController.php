<?php

namespace App\Modules\Project\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Project\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->invoiceService->listInvoices($request);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type' => ['nullable', 'string', 'in:gst_tax_invoice,non_gst_invoice'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string'],
            'company_gstin' => ['nullable', 'string', 'max:25'],
            'company_pan' => ['nullable', 'string', 'max:20'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_inter_state' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:30'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_upi_id' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:draft,issued,partially_paid,paid,overdue,cancelled'],
            'currency' => ['nullable', 'string', 'max:10'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
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

        $invoice = $this->invoiceService->createInvoice($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice created successfully.',
            'data' => $invoice,
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['project', 'quotation', 'createdBy', 'items']);

        return response()->json([
            'status' => 'success',
            'data' => $invoice,
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type' => ['nullable', 'string', 'in:gst_tax_invoice,non_gst_invoice'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_address' => ['nullable', 'string'],
            'client_gstin' => ['nullable', 'string', 'max:25'],
            'place_of_supply' => ['nullable', 'string', 'max:100'],
            'is_inter_state' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:30'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_upi_id' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,issued,partially_paid,paid,overdue,cancelled'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
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

        $updated = $this->invoiceService->updateInvoice($invoice, $validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice updated successfully.',
            'data' => $updated,
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->invoiceService->recordPayment(
            $invoice,
            (float) $validated['amount'],
            $request->user(),
            $validated['note'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Payment recorded successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->deleteInvoice($invoice, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice deleted successfully.',
        ]);
    }
}
