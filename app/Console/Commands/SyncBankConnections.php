<?php

namespace App\Console\Commands;

use App\Actions\SyncBankConnection;
use App\Models\BankConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounts:sync {--demo : Only sync connections for users with demo refresh enabled}')]
#[Description('Refresh tokens and pull the latest balances for active bank connections')]
class SyncBankConnections extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncBankConnection $syncConnection): int
    {
        $connections = BankConnection::query()
            ->syncable()
            ->when($this->option('demo'), fn ($query) => $query->whereHas(
                'user',
                fn ($query) => $query->where('demo_refresh', true),
            ))
            ->get();

        $this->info("Syncing {$connections->count()} connection(s)...");

        $failures = 0;

        foreach ($connections as $connection) {
            try {
                $syncConnection($connection);
            } catch (\Throwable $e) {
                $failures++;
                report($e);
                $connection->update(['status' => 'error']);
                $this->error("Connection [{$connection->id}] failed: {$e->getMessage()}");
            }
        }

        $this->info('Done.');

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
