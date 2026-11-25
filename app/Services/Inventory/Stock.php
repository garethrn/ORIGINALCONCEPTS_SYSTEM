<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;

final class Stock
{
    public function receiveInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('lines');
        foreach ($invoice->lines as $line) {
            $this->move($line->product_id, $this->negate((string) $line->quantity), 'invoice', 'invoice', $invoice->id);
        }
    }

    public function receiveCreditNote(CreditNote $creditNote): void
    {
        $creditNote->loadMissing('lines');
        foreach ($creditNote->lines as $line) {
            $this->move($line->product_id, (string) $line->quantity, 'credit_note', 'credit_note', $creditNote->id);
        }
    }

    public function receiveBill(Bill $bill): void
    {
        $bill->loadMissing('lines');
        foreach ($bill->lines as $line) {
            $this->move($line->product_id, (string) $line->quantity, 'bill', 'bill', $bill->id);
        }
    }

    private function move(?int $productId, string $quantity, string $reason, string $sourceType, int $sourceId): void
    {
        if ($productId === null || bccomp($quantity, '0', 3) === 0) {
            return;
        }
        $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
        if ($product === null || ! $product->track_stock) {
            return;
        }
        $exists = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('reason', $reason)
            ->exists();
        if ($exists) {
            return;
        }
        StockMovement::query()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $product->quantity_on_hand = bcadd((string) $product->quantity_on_hand, $quantity, 3);
        $product->save();
    }

    private function negate(string $quantity): string
    {
        if (str_starts_with($quantity, '-')) {
            return substr($quantity, 1);
        }

        return '-'.$quantity;
    }
}
