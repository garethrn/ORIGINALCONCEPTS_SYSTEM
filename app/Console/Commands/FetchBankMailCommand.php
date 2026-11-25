<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\BankFeedDigest;
use App\Models\BankMailbox;
use App\Services\Banking\BankFeedImporter;
use App\Services\Banking\ImapMailbox;
use App\Services\Billing\CompanyProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FetchBankMailCommand extends Command
{
    protected $signature = 'banking:fetch-mail';

    protected $description = 'Read bank alert emails and allocate what can be matched';

    public function handle(ImapMailbox $imap, BankFeedImporter $importer, CompanyProfile $company): int
    {
        $created = 0;
        $allocated = 0;
        $pending = 0;
        foreach (BankMailbox::query()->where('active', true)->get() as $mailbox) {
            try {
                $messages = $imap->unseen($mailbox);
            } catch (Throwable $exception) {
                $this->error($mailbox->name.': '.$exception->getMessage());
                continue;
            }
            foreach ($messages as $raw) {
                $result = $importer->ingest($raw, $mailbox);
                $created += $result['created'];
                $allocated += $result['allocated'];
                $pending += $result['pending'];
            }
            $mailbox->last_checked_at = now();
            $mailbox->save();
        }
        if ($created > 0 && $company->get('email') !== '') {
            Mail::to($company->get('email'))->send(new BankFeedDigest($allocated, $pending));
        }
        $this->info($created.' bank transaction'.($created === 1 ? '' : 's').' from email.');

        return self::SUCCESS;
    }
}
