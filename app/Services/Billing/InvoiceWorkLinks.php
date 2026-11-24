<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\PartUsed;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

final class InvoiceWorkLinks
{
    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $lineIds
     */
    public function release(Collection|array $lineIds): void
    {
        $ids = collect($lineIds)->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        TimeEntry::query()->whereIn('invoice_line_id', $ids)->update(['invoice_line_id' => null]);
        PartUsed::query()->whereIn('invoice_line_id', $ids)->update(['invoice_line_id' => null]);
    }
}
