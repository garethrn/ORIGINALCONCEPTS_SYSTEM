<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasDocumentLines;

    protected $fillable = [
        'invoice_id', 'product_id', 'description', 'quantity', 'unit_price_cents',
        'price_includes_vat', 'vat_code', 'vat_basis_points', 'net_cents', 'vat_cents',
        'total_cents', 'sort_order',
    ];

    protected function casts(): array
    {
        return $this->documentLineCasts();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
