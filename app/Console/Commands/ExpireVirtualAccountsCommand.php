<?php

namespace App\Console\Commands;

use App\Models\VirtualAccount;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ExpireVirtualAccountsCommand extends Command
{
    protected $signature = 'masscollect:expire';

    protected $description = 'Oznacza mikro rachunki, którym minął termin. Numery NIE wracają do puli.';

    public function handle(): int
    {
        $count = VirtualAccount::query()
            ->whereIn('status', [VirtualAccount::STATUS_ALLOCATED, VirtualAccount::STATUS_ACTIVE])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->update(['status' => VirtualAccount::STATUS_EXPIRED]);

        $this->info(sprintf('Oznaczono %d wygasłych mikro rachunków.', $count));

        return self::SUCCESS;
    }
}
