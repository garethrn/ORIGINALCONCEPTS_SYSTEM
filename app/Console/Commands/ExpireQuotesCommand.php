<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Console\Command;

class ExpireQuotesCommand extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Mark issued quotes past their validity date as expired.';

    public function handle(): int
    {
        $count = Quote::query()
            ->where('status', QuoteStatus::Issued->value)
            ->whereDate('valid_until', '<', today())
            ->update(['status' => QuoteStatus::Expired->value]);

        $this->info($count.' quote(s) expired.');

        return self::SUCCESS;
    }
}
