<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLine extends Model
{
    use HasDocumentLines;

    protected $fillable = [
        'quote_id', 'product_id', 'description', 'quantity', 'unit_price_cents',
        'price_includes_vat', 'vat_code', 'vat_basis_points', 'net_cents', 'vat_cents',
        'total_cents', 'sort_order', 'quote_option_id', 'is_optional', 'option_accepted',
    ];

    protected function casts(): array
    {
        return $this->documentLineCasts() + [
            'is_optional' => 'boolean',
            'option_accepted' => 'boolean',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuoteOption::class, 'quote_option_id');
    }
}
