<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Actions\Billing\EmailDocument;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\IssueQuote;
use App\Actions\Billing\SaveDraftInvoice;
use App\Actions\Billing\SaveDraftQuote;
use App\Actions\Tickets\CreateTicket;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\AiDraft;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmResult
{
    public function __construct(
        public readonly string $message,
        public readonly string $route,
        public readonly int $id,
    ) {}
}

final class ConfirmAiDraft
{
    public function __construct(
        private readonly SaveDraftInvoice $invoices,
        private readonly IssueInvoice $issueInvoice,
        private readonly SaveDraftQuote $quotes,
        private readonly IssueQuote $issueQuote,
        private readonly EmailDocument $mailer,
        private readonly CreateTicket $tickets,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, AiDraft $draft, string $decision): ConfirmResult
    {
        return DB::transaction(function () use ($user, $draft, $decision) {
            $draft = AiDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($draft->user_id !== $user->id || $draft->status !== 'pending' || $draft->expires_at->isPast()) {
                throw ValidationException::withMessages(['draft' => 'This draft is no longer available. Ask the assistant again.']);
            }

            $result = match ($draft->type) {
                'invoice' => $this->invoice($user, $draft->payload, $decision),
                'quote' => $this->quote($user, $draft->payload, $decision),
                'ticket' => $this->ticket($user, $draft->payload, $decision),
                default => throw ValidationException::withMessages(['draft' => 'Unknown draft.']),
            };

            $draft->status = 'confirmed';
            $draft->confirmed_at = now();
            $draft->result_type = $draft->type;
            $draft->result_id = $result->id;
            $draft->save();
            $this->audit->log('ai.confirmed', $draft, null, ['decision' => $decision], $user->id);

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function invoice(User $user, array $payload, string $decision): ConfirmResult
    {
        if (! in_array($decision, ['save', 'issue', 'issue_and_email'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose save, issue, or issue and email.']);
        }
        $validated = StoreInvoiceRequest::fromPayload($user, $payload);
        $invoice = $this->invoices->handle($user, $validated);
        if ($decision === 'save') {
            return new ConfirmResult('Draft invoice saved. It has no invoice number yet.', 'invoices.show', $invoice->id);
        }
        $invoice = $this->issueInvoice->handle($user, $invoice);
        if ($decision === 'issue_and_email') {
            $this->mailer->handle($invoice);
        }

        return new ConfirmResult('Tax invoice '.$invoice->number.' issued.', 'invoices.show', $invoice->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function quote(User $user, array $payload, string $decision): ConfirmResult
    {
        if (! in_array($decision, ['save', 'issue', 'issue_and_email'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose save, issue, or issue and email.']);
        }
        $validated = StoreQuoteRequest::fromPayload($user, $payload);
        $quote = $this->quotes->handle($user, $validated);
        if ($decision === 'save') {
            return new ConfirmResult('Draft quote saved.', 'quotes.show', $quote->id);
        }
        $quote = $this->issueQuote->handle($user, $quote);
        if ($decision === 'issue_and_email') {
            $this->mailer->handle($quote);
        }

        return new ConfirmResult('Quote '.$quote->number.' issued.', 'quotes.show', $quote->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ticket(User $user, array $payload, string $decision): ConfirmResult
    {
        if ($decision !== 'create') {
            throw ValidationException::withMessages(['decision' => 'Confirm to log the ticket.']);
        }
        $validated = StoreTicketRequest::fromPayload($user, $payload);
        $ticket = $this->tickets->handle($user, $validated);

        return new ConfirmResult('Ticket '.$ticket->reference().' logged.', 'tickets.show', $ticket->id);
    }
}
