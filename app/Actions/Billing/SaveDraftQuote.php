<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentLineWriter;
use App\Services\Billing\QuotePackages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDraftQuote
{
    public function __construct(
        private readonly DocumentLineWriter $lines,
        private readonly QuotePackages $packages,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?Quote $quote = null): Quote
    {
        return DB::transaction(function () use ($actor, $data, $quote) {
            if ($quote && ! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'This quote can no longer be edited.']);
            }

            $built = $this->lines->build($data['lines']);
            $quote ??= new Quote(['created_by' => $actor->id]);
            $quote->fill([
                'client_id' => $data['client_id'],
                'contact_id' => $data['contact_id'] ?? null,
                'ticket_id' => $data['ticket_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);
            $quote->status = QuoteStatus::Draft;
            $quote->selected_option_id = null;
            $quote->subtotal_cents = 0;
            $quote->vat_cents = 0;
            $quote->total_cents = 0;
            $quote->save();
            $quote->lines()->delete();
            $quote->options()->delete();

            $optionIds = [];
            foreach ($data['options'] ?? [] as $index => $option) {
                $name = trim((string) ($option['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $optionIds[(int) $index] = $quote->options()->create([
                    'name' => $name,
                    'sort_order' => count($optionIds),
                ])->id;
            }

            foreach (array_values($data['lines']) as $index => $source) {
                $line = $built['lines'][$index];
                unset($line['source_id']);
                $placement = (string) ($source['placement'] ?? 'included');
                $line['is_optional'] = $placement === 'optional';
                $line['option_accepted'] = false;
                $line['quote_option_id'] = str_starts_with($placement, 'option-')
                    ? ($optionIds[(int) substr($placement, 7)] ?? null)
                    : null;
                $quote->lines()->create($line);
            }

            $this->packages->syncTotals($quote);
            $this->audit->log('quote.draft_saved', $quote, null, ['total_cents' => $quote->total_cents], $actor->id);

            return $quote->load('lines', 'client');
        });
    }
}
