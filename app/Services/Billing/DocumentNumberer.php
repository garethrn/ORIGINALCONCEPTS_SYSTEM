<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

final class DocumentNumberer
{
    /**
     * Allocate the next gap-free number. Call this inside the same database
     * transaction as the document update so a rollback does not consume a number.
     */
    public function next(DocumentType $type): string
    {
        $allocate = function () use ($type): string {
            $sequence = DocumentSequence::query()
                ->where('type', $type->value)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $sequence->prefix.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
            $sequence->next_number++;
            $sequence->save();

            return $number;
        };

        if (DB::transactionLevel() === 0) {
            return DB::transaction($allocate);
        }

        return $allocate();
    }
}
