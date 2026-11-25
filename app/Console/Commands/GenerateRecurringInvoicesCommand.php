<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\RunRecurringSchedules;
use Illuminate\Console\Command;

class GenerateRecurringInvoicesCommand extends Command
{
    protected $signature = 'billing:recurring';

    protected $description = 'Create due invoices from recurring schedules';

    public function handle(RunRecurringSchedules $runner): int
    {
        $created = $runner->handle();
        $this->info($created.' recurring invoice'.($created === 1 ? '' : 's').' created.');

        return self::SUCCESS;
    }
}
