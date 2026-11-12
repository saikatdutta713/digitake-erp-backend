<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'project_id',
        'quotation_id',
        'created_by_user_id',
        'client_name',
        'client_company',
        'client_email',
        'client_phone',
        'client_address',
        'client_gstin',
        'company_name',
        'company_address',
        'company_gstin',
        'company_pan',
        'place_of_supply',
        'is_inter_state',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_branch',
        'bank_upi_id',
        'issue_date',
        'due_date',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'taxable_amount',
        'cgst_total',
        'sgst_total',
        'igst_total',
        'grand_total',
        'paid_amount',
        'balance_amount',
        'payment_terms',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_inter_state' => 'boolean',
            'subtotal' => 'float',
            'discount_total' => 'float',
            'taxable_amount' => 'float',
            'cgst_total' => 'float',
            'sgst_total' => 'float',
            'igst_total' => 'float',
            'grand_total' => 'float',
            'paid_amount' => 'float',
            'balance_amount' => 'float',
            'issue_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Generate sequential invoice number.
     */
    public static function generateNumber(string $type = 'gst_tax_invoice'): string
    {
        $prefix = $type === 'gst_tax_invoice' ? 'TAX' : 'INV';
        $year = date('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('%s-%s-%03d', $prefix, $year, $count);
    }

    /**
     * Recalculate invoice totals and taxes.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items;
        $subtotal = 0;
        $discountTotal = 0;
        $taxableAmount = 0;
        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;
        $grandTotal = 0;

        $isGst = $this->invoice_type === 'gst_tax_invoice';

        foreach ($items as $item) {
            $basePrice = $item->quantity * $item->unit_price;
            $disc = $item->discount_amount > 0
                ? $item->discount_amount
                : ($basePrice * ($item->discount_rate / 100));

            $taxable = max(0, $basePrice - $disc);

            $cgst = 0;
            $sgst = 0;
            $igst = 0;

            if ($isGst) {
                if ($this->is_inter_state) {
                    $igst = round($taxable * ($item->tax_rate / 100), 2);
                } else {
                    $halfRate = $item->tax_rate / 2;
                    $cgst = round($taxable * ($halfRate / 100), 2);
                    $sgst = round($taxable * ($halfRate / 100), 2);
                }
            }

            $lineTotal = round($taxable + $cgst + $sgst + $igst, 2);

            $item->update([
                'discount_amount' => round($disc, 2),
                'taxable_value' => round($taxable, 2),
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_amount' => $lineTotal,
            ]);

            $subtotal += $basePrice;
            $discountTotal += $disc;
            $taxableAmount += $taxable;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
            $grandTotal += $lineTotal;
        }

        $balance = max(0, $grandTotal - ($this->paid_amount ?? 0));

        $status = $this->status;
        if ($this->paid_amount >= $grandTotal && $grandTotal > 0) {
            $status = 'paid';
        } elseif ($this->paid_amount > 0 && $this->paid_amount < $grandTotal) {
            $status = 'partially_paid';
        }

        $this->update([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'cgst_total' => round($cgstTotal, 2),
            'sgst_total' => round($sgstTotal, 2),
            'igst_total' => round($igstTotal, 2),
            'grand_total' => round($grandTotal, 2),
            'balance_amount' => round($balance, 2),
            'status' => $status,
        ]);
    }
}
