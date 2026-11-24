<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VatCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id', 'product_id', 'description', 'quantity', 'unit_price_cents',
    'price_includes_vat', 'vat_code', 'billable', 'invoice_line_id',
])]
class PartUsed extends Model
{
    protected $table = 'parts_used';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price_includes_vat' => 'boolean',
            'vat_code' => VatCode::class,
            'billable' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
