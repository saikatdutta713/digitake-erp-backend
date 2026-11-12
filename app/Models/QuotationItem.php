<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quotation_id',
        'item_name',
        'description',
        'hsn_sac_code',
        'quantity',
        'unit',
        'unit_price',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'taxable_value',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'discount_rate' => 'float',
            'discount_amount' => 'float',
            'tax_rate' => 'float',
            'taxable_value' => 'float',
            'cgst_amount' => 'float',
            'sgst_amount' => 'float',
            'igst_amount' => 'float',
            'total_amount' => 'float',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
