<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quotation_number',
        'project_id',
        'created_by_user_id',
        'client_name',
        'client_contact_person',
        'client_company',
        'client_email',
        'client_phone',
        'client_address',
        'client_gstin',
        'company_details',
        'title',
        'project_overview',
        'status',
        'currency',
        'tax_type',
        'is_inter_state',
        'place_of_supply',
        'subtotal',
        'discount_total',
        'taxable_amount',
        'cgst_total',
        'sgst_total',
        'igst_total',
        'grand_total',
        'issue_date',
        'valid_until',
        'terms_and_conditions',
        'notes',
        'scope_of_work',
        'tech_stack',
        'timeline_phases',
        'payment_milestones',
        'support_terms',
        'signatory_details',
        'converted_to_project_id',
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
            'issue_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'company_details' => 'array',
            'project_overview' => 'array',
            'scope_of_work' => 'array',
            'tech_stack' => 'array',
            'timeline_phases' => 'array',
            'payment_milestones' => 'array',
            'support_terms' => 'array',
            'signatory_details' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function convertedToProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_to_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Generate sequential quotation number.
     */
    public static function generateNumber(): string
    {
        $year = date('Y');
        $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('QT-%s-%03d', $year, $count);
    }

    /**
     * Recalculate quotation line item sums and tax breakdown.
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

        foreach ($items as $item) {
            $basePrice = $item->quantity * $item->unit_price;
            $disc = $item->discount_amount > 0
                ? $item->discount_amount
                : ($basePrice * ($item->discount_rate / 100));

            $taxable = max(0, $basePrice - $disc);

            $cgst = 0;
            $sgst = 0;
            $igst = 0;

            if ($this->tax_type === 'gst') {
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

        $this->update([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'cgst_total' => round($cgstTotal, 2),
            'sgst_total' => round($sgstTotal, 2),
            'igst_total' => round($igstTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ]);
    }
}
