<?php

declare(strict_types=1);

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankEmail;
use App\Models\BankFeedNotice;
use App\Models\BankMailbox;
use App\Models\BankRule;
use App\Models\BankTransaction;

final class BankFeedImporter
{
    public function __construct(
        private readonly BankAlertRegistry $parsers,
        private readonly TransactionMatcher $matcher,
        private readonly TransactionReviewer $reviewer,
    ) {}

    /**
     * @return array{created: int, allocated: int, pending: int, duplicate: bool}
     */
    public function ingest(string $raw, ?BankMailbox $mailbox = null): array
    {
        $email = IncomingEmail::fromRaw($raw);
        $hash = hash('sha256', $email->messageId !== '' ? 'id:'.$email->messageId : $raw);
        if (BankEmail::query()->where('body_hash', $hash)->exists()) {
            return ['created' => 0, 'allocated' => 0, 'pending' => 0, 'duplicate' => true];
        }
        if ($mailbox !== null && ! $this->allowed($mailbox, $email->from)) {
            BankEmail::query()->create([
                'bank_mailbox_id' => $mailbox->id,
                'message_id' => $email->messageId ?: null,
                'from_address' => $email->from,
                'subject' => $email->subject,
                'received_at' => now(),
                'raw_body' => $raw,
                'body_hash' => $hash,
                'status' => 'ignored',
            ]);

            return ['created' => 0, 'allocated' => 0, 'pending' => 0, 'duplicate' => false];
        }

        $stored = BankEmail::query()->create([
            'bank_mailbox_id' => $mailbox?->id,
            'message_id' => $email->messageId ?: null,
            'from_address' => $email->from,
            'subject' => $email->subject,
            'received_at' => now(),
            'raw_body' => $raw,
            'body_hash' => $hash,
            'status' => 'processed',
        ]);

        $rows = $this->parsers->parse($email->from, $email->subject, $email->body);
        $parser = $rows['parser'];
        $parsed = $rows['rows'];
        foreach ($email->attachments as $attachment) {
            $parsed = array_merge($parsed, $this->attachmentRows($attachment['name'], $attachment['content'], $email->body));
        }
        $parsed = $this->uniqueRows($parsed);

        $created = 0;
        $allocated = 0;
        $pending = 0;
        foreach ($parsed as $index => $row) {
            $account = $this->account($row['last4']);
            if ($account === null) {
                BankFeedNotice::query()->create([
                    'kind' => 'unmatched-account',
                    'body' => 'A bank alert could not be filed because no bank account ends in '.($row['last4'] ?: 'an unknown number').'.',
                ]);
                continue;
            }
            $transaction = BankTransaction::query()->create([
                'bank_account_id' => $account->id,
                'posted_on' => $row['posted_on'],
                'description' => mb_substr($row['description'], 0, 255),
                'amount_cents' => $row['amount_cents'],
                'reference' => $row['reference'] !== '' ? mb_substr($row['reference'], 0, 255) : null,
                'import_hash' => hash('sha256', $hash.'|'.$index.'|'.$row['amount_cents'].'|'.$row['posted_on']),
                'bank_email_id' => $stored->id,
                'review_status' => 'pending',
                'parser' => $parser,
            ]);
            $suggestion = $this->matcher->suggest($transaction);
            $transaction->confidence = $suggestion['confidence'];
            $transaction->suggestion = $suggestion['label'] !== '' ? $suggestion['label'] : null;
            $transaction->save();
            $created++;
            if ($this->autoAllocate($mailbox, $transaction, $suggestion)) {
                $allocated++;
            } else {
                $pending++;
            }
        }

        if ($created > 0) {
            BankFeedNotice::query()->create([
                'kind' => 'digest',
                'body' => $allocated.' bank transaction'.($allocated === 1 ? '' : 's').' allocated automatically. '.$pending.' still need review.',
            ]);
        }

        return ['created' => $created, 'allocated' => $allocated, 'pending' => $pending, 'duplicate' => false];
    }

    /**
     * @param  array{confidence: int, label: string, invoice_ids: list<int>, bill_id: ?int, rule_id: ?int}  $suggestion
     */
    private function autoAllocate(?BankMailbox $mailbox, BankTransaction $transaction, array $suggestion): bool
    {
        $threshold = $mailbox?->confidence_threshold ?? 90;
        $enabled = $mailbox?->auto_allocate ?? true;
        if ($transaction->amount_cents > 0 && $enabled && $suggestion['confidence'] >= $threshold && count($suggestion['invoice_ids']) === 1) {
            $this->reviewer->allocateInvoices($transaction, $suggestion['invoice_ids'], null);

            return true;
        }
        if ($transaction->amount_cents < 0 && $suggestion['rule_id'] !== null && $suggestion['bill_id'] === null) {
            $rule = BankRule::query()->find($suggestion['rule_id']);
            if ($rule !== null) {
                $this->reviewer->assignExpense($transaction, (int) $rule->account_id);

                return true;
            }
        }

        return false;
    }

    private function allowed(BankMailbox $mailbox, string $from): bool
    {
        foreach (['fnb.co.za', 'standardbank.co.za', 'absa.co.za', 'absa.africa', 'nedbank.co.za', 'capitecbank.co.za', 'capitec.co.za', 'investec.co.za', 'investec.com'] as $domain) {
            if (str_ends_with($from, '@'.$domain) || str_ends_with($from, '.'.$domain)) {
                return true;
            }
        }

        return in_array($from, $mailbox->senderAllowList(), true);
    }

    private function account(?string $last4): ?BankAccount
    {
        if ($last4 !== null) {
            return BankAccount::query()->where('account_last4', $last4)->first();
        }
        $accounts = BankAccount::query()->where('active', true)->get();

        return $accounts->count() === 1 ? $accounts->first() : null;
    }

    /**
     * @param  list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>  $rows
     * @return list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>
     */
    private function uniqueRows(array $rows): array
    {
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = $row['posted_on'].'|'.$row['amount_cents'].'|'.($row['last4'] ?? '').'|'.$row['reference'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @return list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>
     */
    private function attachmentRows(string $name, string $content, string $fallback): array
    {
        $lower = strtolower($name);
        if (str_contains($lower, '.ofx') || str_contains($content, '<STMTTRN>')) {
            return $this->ofx($content, $fallback);
        }
        if (str_contains($lower, '.csv') || str_contains(strtolower($content), 'date,description,amount')) {
            return $this->csv($content, $fallback);
        }
        $parsed = $this->parsers->parse('', $name, $content);

        return $parsed['rows'];
    }

    /**
     * @return list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>
     */
    private function csv(string $content, string $fallback): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if ($lines === []) {
            return [];
        }
        $header = array_map(fn ($cell) => strtolower(trim($cell)), str_getcsv(array_shift($lines)));
        $map = array_flip($header);
        $last4 = (new Parsers\GenericAlertParser)->parse($fallback)[0]['last4'] ?? null;
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $amount = (int) round(((float) str_replace([',', ' '], '', $cells[$map['amount'] ?? 2] ?? '0')) * 100);
            if ($amount === 0) {
                continue;
            }
            $description = $cells[$map['description'] ?? 1] ?? 'Statement line';
            $rows[] = [
                'amount_cents' => $amount,
                'posted_on' => $cells[$map['date'] ?? 0] ?? today()->toDateString(),
                'description' => $description,
                'reference' => preg_match('/INV-\d+/', $description, $match) === 1 ? $match[0] : '',
                'direction' => $amount < 0 ? 'out' : 'in',
                'last4' => $last4,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{amount_cents: int, posted_on: string, description: string, reference: string, direction: string, last4: ?string}>
     */
    private function ofx(string $content, string $fallback): array
    {
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches);
        $last4 = (new Parsers\GenericAlertParser)->parse($fallback)[0]['last4'] ?? null;
        $rows = [];
        foreach ($matches[1] as $block) {
            preg_match('/<TRNAMT>([^<\r\n]+)/', $block, $amount);
            preg_match('/<DTPOSTED>(\d{8})/', $block, $date);
            preg_match('/<(?:NAME|MEMO)>([^<\r\n]+)/', $block, $name);
            $cents = (int) round(((float) ($amount[1] ?? 0)) * 100);
            if ($cents === 0) {
                continue;
            }
            $description = trim($name[1] ?? 'Statement line');
            $rows[] = [
                'amount_cents' => $cents,
                'posted_on' => isset($date[1]) ? substr($date[1], 0, 4).'-'.substr($date[1], 4, 2).'-'.substr($date[1], 6, 2) : today()->toDateString(),
                'description' => $description,
                'reference' => preg_match('/INV-\d+/', $description, $match) === 1 ? $match[0] : '',
                'direction' => $cents < 0 ? 'out' : 'in',
                'last4' => $last4,
            ];
        }

        return $rows;
    }
}
