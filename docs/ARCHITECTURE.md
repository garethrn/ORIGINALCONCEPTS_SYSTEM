# Architecture

Laravel 13, Blade, Tailwind, and a small amount of plain JavaScript. There is no separate front-end app. Dompdf renders PDFs. The queue and the scheduler use the database driver.

## Request path

HTTP requests hit form requests, then policies, then an action class. Controllers stay thin. Models list the columns that can be mass-assigned. Status, document numbers, and money totals are set in the action, not from the form.

Policies cover customers, tickets, quotes, invoices, credit notes, payments, products, users, and assistant drafts. A client user is allowed to see a ticket only when `tickets.client_id` equals `users.client_id`.

## Money and tax

Amounts are integer cents. `App\Services\Billing\VatCalculator` prices a line exclusive or inclusive of VAT and rounds half up. Standard VAT is 15% (1500 basis points). Zero-rated and exempt lines store a zero VAT amount and keep the code so the PDF can say which is which.

`DocumentNumberer` locks the matching row in `document_sequences` and increments it in the same database transaction as the issue. A failed issue rolls the number back. Drafts have no number.

An issued invoice is locked. `InvoicePolicy::update` refuses it. The settlement status (part paid, paid, credited) changes only when a payment is allocated or a credit note is issued.

A tax invoice PDF shows the words Tax Invoice, the supplier name, address, and VAT number, the customer name, address, and VAT number, the serial number, the issue date, and each line's description, quantity, value, VAT, and total. A credit note says Credit Note and names the original invoice.

Company details live in the `settings` table and are edited by an admin. Issuing an invoice or credit note is refused until the company VAT number is saved.

## Tickets

`TicketStatus` allows only these moves:

- open → in progress, waiting, resolved, cancelled
- in progress → open, waiting, resolved, cancelled
- waiting → in progress, resolved, cancelled
- resolved → closed, in progress
- closed → in progress
- cancelled → none

Files are stored on the private disk under random names and downloaded through an authorized route. Internal comments and their files are hidden from client users. Time entries and parts can be copied onto a draft invoice.

## Assistant

`App\Services\Ai\Assistant` calls a `ChatClient`. The OpenAI-compatible client posts to `AI_BASE_URL` with `AI_API_KEY` and `AI_MODEL`. `AI_DRIVER=fake` uses a local stand-in for tests and demos and must not be used in production.

The model may call `search_customers`, `search_products`, `create_invoice_draft`, `create_quote_draft`, and `create_ticket`. Billing tools are omitted unless the person's role can bill. Each create tool resolves names in PHP, then validates the payload with the same form request the screen uses, and checks the same policy. The result is an `ai_drafts` row and a preview. Email and issue run only when that person submits the confirm checkbox.

User text is length-limited, stored with normal Blade escaping, and written to the audit log. The assistant routes are rate-limited. With no API key the page explains that the assistant is off and does not call a provider.

## Later payments

`OnlinePaymentGateway` and `RecurringBillingGateway` are bound to unavailable implementations. PayFast and Yoco webhook routes exist, are rate-limited, skip CSRF the way a provider callback must, and return 404. They do not contact either provider.

## Mail and jobs

Ticket notices and document PDFs are queued mailables. The scheduler expires quotes and sends an overdue reminder at most once a week. Both run in the `scheduler` container. The `queue` container runs `queue:work`.

## Security headers and the installed app

`SecurityHeaders` sends a content security policy that allows the same-origin manifest and service worker, plus the usual frame, sniffing, and referrer headers. HSTS is sent only on HTTPS responses and does not include subdomains, so it does not change other hostnames on `originalconcepts.co.za`.

`/sw.js` is generated with a version hash of the Vite manifest and icons, `Cache-Control: no-cache`, and `Service-Worker-Allowed: /`. The worker caches the shell and versioned files, uses the network first for HTML, and does not store POST requests, `/assistant`, `/health`, or responses marked private.
