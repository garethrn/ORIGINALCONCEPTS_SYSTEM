<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\VatCode;
use App\Support\Money;

trait HasDocumentLines
{
    protected function documentLineCasts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price_includes_vat' => 'boolean',
            'vat_code' => VatCode::class,
        ];
    }

    public function unitPriceRands(): string
    {
        return Money::centsToRands((int) $this->unit_price_cents);
    }
}
