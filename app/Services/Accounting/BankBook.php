<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankRule;
use App\Models\BankTransaction;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BankBook
{
    public function __construct(private readonly Ledger $ledger) {}

    public function import(BankAccount $account, string $contents, string $format): int
    {
        $rows = $format === 'ofx' ? $this->ofx($contents) : $this->csv($contents);
        $created = 0;
        foreach ($rows as $row) {
            $hash = hash('sha256', $account->id.'|'.$row['fit'].'|'.$row['date'].'|'.$row['amount'].'|'.$row['description']);
            if (BankTransaction::query()->where('import_hash', $hash)->exists()) {
                continue;
            }
            $transaction = BankTransaction::query()->create([
                'bank_account_id' => $account->id,
                'posted_on' => $row['date'],
                'description' => $row['description'],
                'amount_cents' => $row['amount'],
                'reference' => $row['fit'],
                'import_hash' => $hash,
            ]);
            $this->applyRule($account, $transaction);
            $created++;
        }

        return $created;
    }

    public function match(BankTransaction $transaction, JournalLine $line): void
    {
        $bankAccountId = (int) $transaction->bankAccount->account_id;
        if ((int) $line->account_id !== $bankAccountId) {
            throw ValidationException::withMessages(['match' => 'That journal line is not on this bank account.']);
        }
        if ($line->signedCents() !== (int) $transaction->amount_cents) {
            throw ValidationException::withMessages(['match' => 'The amounts do not match.']);
        }
        if (BankTransaction::query()->where('journal_line_id', $line->id)->whereKeyNot($transaction->id)->exists()) {
            throw ValidationException::withMessages(['match' => 'That journal line is already matched.']);
        }
        $transaction->journal_line_id = $line->id;
        $transaction->save();
    }

    /**
     * @param  list<int>  $transactionIds
     */
    public function reconcile(BankAccount $account, string $date, int $statementBalance, array $transactionIds, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($account, $date, $statementBalance, $transactionIds, $actor) {
            $opening = (int) BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->whereNotNull('reconciliation_id')
                ->sum('amount_cents');
            $ticked = BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->whereNull('reconciliation_id')
                ->whereIn('id', $transactionIds)
                ->get();
            $cleared = $opening + (int) $ticked->sum('amount_cents');
            if ($cleared !== $statementBalance) {
                throw ValidationException::withMessages([
                    'statement_balance' => 'The ticked transactions do not equal the statement balance. Difference: '.number_format(($statementBalance - $cleared) / 100, 2, '.', ''),
                ]);
            }
            $reconciliation = BankReconciliation::query()->create([
                'bank_account_id' => $account->id,
                'statement_date' => $date,
                'statement_balance_cents' => $statementBalance,
                'created_by' => $actor->id,
            ]);
            BankTransaction::query()->whereIn('id', $ticked->pluck('id'))->update(['reconciliation_id' => $reconciliation->id]);

            return $reconciliation;
        });
    }

    /**
     * @return list<JournalLine>
     */
    public function suggestions(BankTransaction $transaction): array
    {
        $accountId = $transaction->bankAccount->account_id;
        $matched = BankTransaction::query()->whereNotNull('journal_line_id')->pluck('journal_line_id');

        return JournalLine::query()
            ->where('account_id', $accountId)
            ->whereNotIn('id', $matched)
            ->whereRaw('(debit_cents - credit_cents) = ?', [$transaction->amount_cents])
            ->whereHas('entry', function ($query) use ($transaction) {
                $query->whereBetween('entry_date', [
                    $transaction->posted_on->copy()->subDays(5)->toDateString(),
                    $transaction->posted_on->copy()->addDays(5)->toDateString(),
                ]);
            })
            ->with('entry')
            ->limit(5)
            ->get()
            ->all();
    }

    private function applyRule(BankAccount $account, BankTransaction $transaction): void
    {
        $rule = BankRule::query()
            ->where(function ($query) use ($account) {
                $query->whereNull('bank_account_id')->orWhere('bank_account_id', $account->id);
            })
            ->get()
            ->first(fn (BankRule $rule) => str_contains(mb_strtolower($transaction->description), mb_strtolower($rule->contains)));
        if ($rule === null) {
            return;
        }
        $other = Account::query()->find($rule->account_id);
        if ($other === null) {
            return;
        }
        $amount = (int) $transaction->amount_cents;
        $entry = $this->ledger->post('bank_rule', $transaction->id, $transaction->posted_on->toDateString(), $transaction->description, [
            [
                'account_id' => $account->account_id,
                'debit_cents' => $amount > 0 ? $amount : 0,
                'credit_cents' => $amount < 0 ? abs($amount) : 0,
                'description' => $transaction->description,
                'client_id' => null,
                'supplier_id' => null,
            ],
            [
                'account_id' => $other->id,
                'debit_cents' => $amount < 0 ? abs($amount) : 0,
                'credit_cents' => $amount > 0 ? $amount : 0,
                'description' => $transaction->description,
                'client_id' => null,
                'supplier_id' => null,
            ],
        ], null);
        $bankLine = $entry->lines->firstWhere('account_id', $account->account_id);
        if ($bankLine !== null) {
            $transaction->journal_line_id = $bankLine->id;
            $transaction->save();
        }
    }

    /**
     * @return list<array{date: string, description: string, amount: int, fit: string}>
     */
    private function csv(string $contents): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($contents)) ?: [];
        if ($lines === []) {
            return [];
        }
        $header = str_getcsv(array_shift($lines));
        $map = [];
        foreach ($header as $index => $name) {
            $map[strtolower(trim($name))] = $index;
        }
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $date = $this->date($cells[$map['date'] ?? 0] ?? '');
            $description = trim((string) ($cells[$map['description'] ?? $map['memo'] ?? 1] ?? ''));
            if (isset($map['amount'])) {
                $amount = $this->cents((string) $cells[$map['amount']]);
            } else {
                $credit = $this->cents((string) ($cells[$map['credit'] ?? -1] ?? '0'));
                $debit = $this->cents((string) ($cells[$map['debit'] ?? -1] ?? '0'));
                $amount = $credit - $debit;
            }
            if ($date === null || $description === '' || $amount === 0) {
                continue;
            }
            $rows[] = ['date' => $date, 'description' => $description, 'amount' => $amount, 'fit' => $date.'|'.$amount.'|'.$description];
        }

        return $rows;
    }

    /**
     * @return list<array{date: string, description: string, amount: int, fit: string}>
     */
    private function ofx(string $contents): array
    {
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $contents, $matches);
        $rows = [];
        foreach ($matches[1] as $block) {
            $date = $this->date($this->tag($block, 'DTPOSTED') ?? '');
            $amount = $this->cents($this->tag($block, 'TRNAMT') ?? '0');
            $description = trim($this->tag($block, 'NAME') ?: ($this->tag($block, 'MEMO') ?: 'Bank transaction'));
            $fit = $this->tag($block, 'FITID') ?: $date.'|'.$amount.'|'.$description;
            if ($date === null || $amount === 0) {
                continue;
            }
            $rows[] = ['date' => $date, 'description' => $description, 'amount' => $amount, 'fit' => $fit];
        }

        return $rows;
    }

    private function tag(string $block, string $name): ?string
    {
        if (preg_match('/<'.$name.'>([^<\r\n]+)/', $block, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    private function date(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $match) === 1) {
            return $match[1].'-'.$match[2].'-'.$match[3];
        }
        $parsed = strtotime($value);

        return $parsed === false ? null : date('Y-m-d', $parsed);
    }

    private function cents(string $value): int
    {
        $value = str_replace([',', ' '], '', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }
}
