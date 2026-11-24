<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\Billing\DocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $documentClass, public int $documentId) {}

    public function envelope(): Envelope
    {
        $document = $this->document();
        $label = match ($document::class) {
            Invoice::class => 'Tax invoice',
            Quote::class => 'Quotation',
            default => 'Credit note',
        };

        return new Envelope(subject: $label.' '.$document->number);
    }

    public function content(): Content
    {
        $document = $this->document();

        return new Content(
            view: 'mail.document',
            with: [
                'document' => $document,
                'label' => match ($document::class) {
                    Invoice::class => 'tax invoice',
                    Quote::class => 'quotation',
                    default => 'credit note',
                },
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $document = $this->document();
        $pdf = app(DocumentPdf::class)->render($document);

        return [
            Attachment::fromData(fn () => $pdf, $document->number.'.pdf')->withMime('application/pdf'),
        ];
    }

    private function document(): Invoice|Quote|CreditNote
    {
        /** @var class-string<Invoice|Quote|CreditNote> $class */
        $class = $this->documentClass;

        return $class::query()->with(['client', 'lines'])->findOrFail($this->documentId);
    }
}
