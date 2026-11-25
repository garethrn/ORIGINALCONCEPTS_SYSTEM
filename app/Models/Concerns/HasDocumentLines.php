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

    public function vatLabel(): string
    {
        if ($this->vat_code === VatCode::Standard) {
            $text = rtrim(rtrim(number_format(((int) $this->vat_basis_points) / 100, 2, '.', ''), '0'), '.');

            return $text.'%';
        }

        return $this->vat_code->shortLabel();
    }
}
