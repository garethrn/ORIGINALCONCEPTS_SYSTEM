<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\DocumentType;
use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentNumberer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueQuote
{
    public function __construct(
        private readonly DocumentNumberer $numbers,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, Quote $quote): Quote
    {
        return DB::transaction(function () use ($actor, $quote) {
            $quote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($quote->status !== QuoteStatus::Draft) {
                throw ValidationException::withMessages(['quote' => 'Only a draft quote can be issued.']);
            }

            $quote->number = $this->numbers->next(DocumentType::Quote);
            $quote->status = QuoteStatus::Issued;
            $quote->issued_at = now();
            $quote->issue_date = today();
            $quote->valid_until = $quote->valid_until ?? today()->addDays(14);
            $quote->issued_by = $actor->id;
            $quote->save();
            $this->audit->log('quote.issued', $quote, null, ['number' => $quote->number], $actor->id);

            return $quote->fresh(['lines', 'client']);
        });
    }
}
