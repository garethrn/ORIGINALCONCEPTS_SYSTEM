<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\QuotePackages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChooseQuoteOption
{
    public function __construct(
        private readonly QuotePackages $packages,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>  $optionalLineIds
     */
    public function handle(User $actor, Quote $quote, ?int $optionId, array $optionalLineIds): Quote
    {
        if (! in_array($quote->status, [QuoteStatus::Issued, QuoteStatus::Accepted], true)) {
            throw ValidationException::withMessages([
                'quote' => 'Issue the quote before a customer chooses an option.',
            ]);
        }

        return DB::transaction(function () use ($actor, $quote, $optionId, $optionalLineIds) {
            $quote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $quote->load('options', 'lines');
            if ($quote->options->isNotEmpty()) {
                $option = $quote->options->firstWhere('id', $optionId);
                if ($option === null) {
                    throw ValidationException::withMessages(['option_id' => 'Choose one of the quoted options.']);
                }
                $quote->selected_option_id = $option->id;
            }

            $accepted = collect($optionalLineIds)->map(fn ($id) => (int) $id);
            foreach ($quote->lines as $line) {
                if (! $line->is_optional) {
                    continue;
                }
                $line->option_accepted = $accepted->contains($line->id);
                $line->save();
            }

            $quote->status = QuoteStatus::Accepted;
            $quote->save();
            $this->packages->syncTotals($quote->fresh());
            $this->audit->log('quote.option_chosen', $quote, null, [
                'option_id' => $quote->selected_option_id,
                'optional_line_ids' => $accepted->all(),
            ], $actor->id);

            return $quote->fresh(['lines', 'options', 'client']);
        });
    }
}
