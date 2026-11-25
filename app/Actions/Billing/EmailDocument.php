<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Mail\DocumentMail;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class EmailDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Model $document): void
    {
        if (! $document instanceof Invoice && ! $document instanceof Quote && ! $document instanceof CreditNote) {
            throw ValidationException::withMessages(['document' => 'This document cannot be emailed.']);
        }
        if ($document->isDraft() || blank($document->number)) {
            throw ValidationException::withMessages(['document' => 'Issue the document before emailing it.']);
        }

        $document->loadMissing('client', 'contact');
        $email = $document->contact?->email ?: $document->client?->email;
        if (! $email) {
            throw ValidationException::withMessages(['email' => 'This customer has no email address.']);
        }

        Mail::to($email)->queue((new DocumentMail($document::class, (int) $document->getKey()))->afterCommit());
        $this->audit->log('document.emailed', $document, null, ['to' => $email]);
    }
}
