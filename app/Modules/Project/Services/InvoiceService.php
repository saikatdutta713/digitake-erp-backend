<?php

namespace App\Modules\Project\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Modules\Audit\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InvoiceService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List invoices with financial metrics, filters, and line items.
     *
     * @return array<string, mixed>
     */
    public function listInvoices(Request $request): array
    {
        $query = Invoice::with(['project', 'quotation', 'createdBy', 'items'])
            ->latest('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('invoice_type') && $request->invoice_type !== 'all') {
            $query->where('invoice_type', $request->invoice_type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_company', 'like', "%{$search}%")
                    ->orWhere('client_gstin', 'like', "%{$search}%");
            });
        }

        $records = $query->get();

        $all = Invoice::all();
        $totalInvoiced = $all->where('status', '!=', 'cancelled')->sum('grand_total');
        $totalCollected = $all->where('status', '!=', 'cancelled')->sum('paid_amount');
        $totalOutstanding = $all->where('status', '!=', 'cancelled')->sum('balance_amount');
        $overdueCount = $all->where('status', 'issued')
            ->where('due_date', '<', Carbon::today()->toDateString())
            ->count();

        $stats = [
            'total_invoices' => $all->count(),
            'total_invoiced' => round($totalInvoiced, 2),
            'total_collected' => round($totalCollected, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'paid_count' => $all->where('status', 'paid')->count(),
            'unpaid_count' => $all->whereIn('status', ['issued', 'partially_paid'])->count(),
            'overdue_count' => $overdueCount,
            'collection_rate' => $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0,
        ];

        return [
            'stats' => $stats,
            'count' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Create an invoice (GST Tax Invoice or Non-GST Commercial Invoice).
     *
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(array $data, User $actor): Invoice
    {
        $type = $data['invoice_type'] ?? 'gst_tax_invoice';
        $data['invoice_number'] = $data['invoice_number'] ?? Invoice::generateNumber($type);
        $data['created_by_user_id'] = $actor->id;
        $data['issue_date'] = $data['issue_date'] ?? Carbon::today()->toDateString();
        $data['due_date'] = $data['due_date'] ?? Carbon::today()->addDays(15)->toDateString();
        $data['status'] = $data['status'] ?? 'issued';
        $data['currency'] = $data['currency'] ?? 'INR';
        $data['is_inter_state'] = (bool) ($data['is_inter_state'] ?? false);
        $data['paid_amount'] = (float) ($data['paid_amount'] ?? 0);

        $items = $data['items'] ?? [];
        unset($data['items']);

        $invoice = Invoice::create($data);

        foreach ($items as $item) {
            $invoice->items()->create([
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'hsn_sac_code' => $item['hsn_sac_code'] ?? '998314',
                'quantity' => $item['quantity'] ?? 1,
                'unit' => $item['unit'] ?? 'units',
                'unit_price' => $item['unit_price'] ?? 0,
                'discount_rate' => $item['discount_rate'] ?? 0,
                'tax_rate' => $type === 'gst_tax_invoice' ? ($item['tax_rate'] ?? 18.00) : 0,
            ]);
        }

        $invoice->recalculateTotals();

        $this->auditService->log(
            'invoice.created',
            $invoice,
            null,
            $invoice->toArray(),
            $actor
        );

        return $invoice->fresh(['project', 'quotation', 'createdBy', 'items']);
    }

    /**
     * Update existing invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(Invoice $invoice, array $data, User $actor): Invoice
    {
        $old = $invoice->toArray();

        $items = $data['items'] ?? null;
        unset($data['items']);

        $invoice->update($data);

        if ($items !== null) {
            $invoice->items()->delete();
            $type = $invoice->invoice_type;
            foreach ($items as $item) {
                $invoice->items()->create([
                    'item_name' => $item['item_name'],
                    'description' => $item['description'] ?? null,
                    'hsn_sac_code' => $item['hsn_sac_code'] ?? '998314',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $item['unit'] ?? 'units',
                    'unit_price' => $item['unit_price'] ?? 0,
                    'discount_rate' => $item['discount_rate'] ?? 0,
                    'tax_rate' => $type === 'gst_tax_invoice' ? ($item['tax_rate'] ?? 18.00) : 0,
                ]);
            }
        }

        $invoice->recalculateTotals();

        $this->auditService->log(
            'invoice.updated',
            $invoice,
            $old,
            $invoice->toArray(),
            $actor
        );

        return $invoice->fresh(['project', 'quotation', 'createdBy', 'items']);
    }

    /**
     * Record payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, float $amount, User $actor, ?string $note = null): Invoice
    {
        $old = $invoice->toArray();

        $newPaid = ($invoice->paid_amount ?? 0) + $amount;
        $invoice->paid_amount = min($newPaid, $invoice->grand_total);
        $invoice->recalculateTotals();

        if ($note) {
            $invoice->notes = trim(($invoice->notes ? $invoice->notes."\n" : '')."[Payment Received: ₹{$amount}] ".$note);
            $invoice->save();
        }

        $this->auditService->log(
            'invoice.payment_recorded',
            $invoice,
            $old,
            [
                'amount_paid' => $amount,
                'total_paid' => $invoice->paid_amount,
                'balance' => $invoice->balance_amount,
                'status' => $invoice->status,
            ],
            $actor
        );

        return $invoice->fresh(['project', 'quotation', 'createdBy', 'items']);
    }

    /**
     * Delete invoice.
     */
    public function deleteInvoice(Invoice $invoice, User $actor): bool
    {
        $old = $invoice->toArray();
        $invoice->delete();

        $this->auditService->log('invoice.deleted', $invoice, $old, null, $actor);

        return true;
    }
}
