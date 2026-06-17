<?php

namespace App\Actions;

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\OneSignal;
use App\Services\TrueLayer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RuntimeException;

class SyncBankConnection
{
    public function __construct(
        private readonly TrueLayer $trueLayer,
        private readonly OneSignal $oneSignal,
    ) {}

    /**
     * Refresh the access token if needed, then pull the latest accounts and balances.
     *
     * Passing $customerPresent forwards the connection's stored PSU IP as
     * X-PSU-IP, used by demo-mode background syncs to lift the AIS rate limit.
     */
    public function __invoke(BankConnection $connection, bool $customerPresent = false): void
    {
        $psuIp = $customerPresent ? $connection->psu_ip : null;

        $accessToken = $this->freshAccessToken($connection);

        $accounts = $this->trueLayer->accounts($accessToken, $psuIp);

        $provider = $accounts[0]['provider'] ?? [];

        $connection->update([
            'provider_id' => $provider['provider_id'] ?? $connection->provider_id,
            'provider_name' => $provider['display_name'] ?? $connection->provider_name,
            'logo_uri' => $provider['logo_uri'] ?? $connection->logo_uri,
            'last_synced_at' => Carbon::now(),
        ]);

        /** @var array<int, BankTransaction> $newTransactions */
        $newTransactions = [];

        foreach ($accounts as $account) {
            $balance = $this->trueLayer->balance($accessToken, $account['account_id'], $psuIp);

            $accountModel = $connection->accounts()->updateOrCreate([
                'truelayer_account_id' => $account['account_id'],
            ], [
                'user_id' => $connection->user_id,
                'display_name' => $account['display_name'] ?? null,
                'account_type' => $account['account_type'] ?? null,
                'currency' => $account['currency'] ?? null,
                'account_number' => $account['account_number']['number'] ?? null,
                'sort_code' => $account['account_number']['sort_code'] ?? null,
                'iban' => $account['account_number']['iban'] ?? null,
                'current_balance' => $balance['current'] ?? null,
                'available_balance' => $balance['available'] ?? null,
                'last_synced_at' => Carbon::now(),
            ]);

            try {
                $newTransactions = array_merge(
                    $newTransactions,
                    $this->syncTransactions($accountModel, $accessToken, $psuIp),
                );
            } catch (\Throwable $e) {
                // A connection consented before the `transactions` scope was granted
                // will 403 here — keep the balance sync and surface the cause in logs.
                report($e);
            }
        }

        $this->notifyNewTransactions($connection, $newTransactions);
    }

    /**
     * Pull and persist settled and pending transactions, preserving all fields.
     *
     * @return array<int, BankTransaction> Transactions created during this sync.
     */
    private function syncTransactions(BankAccount $account, string $accessToken, ?string $psuIp = null): array
    {
        $created = [];

        // Settled transactions first: if a previously pending transaction has
        // settled under the same id, this flips its status before cleanup.
        foreach ($this->trueLayer->transactions($accessToken, $account->truelayer_account_id, $psuIp) as $transaction) {
            $model = $this->upsertTransaction($account, $transaction, 'settled');

            if ($model->wasRecentlyCreated) {
                $created[] = $model;
            }
        }

        // Pending transactions (e.g. a card payment that settles later in the day).
        $pendingIds = [];

        foreach ($this->trueLayer->pendingTransactions($accessToken, $account->truelayer_account_id, $psuIp) as $transaction) {
            $pendingIds[] = $transaction['transaction_id'];
            $model = $this->upsertTransaction($account, $transaction, 'pending');

            if ($model->wasRecentlyCreated) {
                $created[] = $model;
            }
        }

        // Drop pending rows that have since settled or disappeared.
        $account->transactions()
            ->where('status', 'pending')
            ->whereNotIn('truelayer_transaction_id', $pendingIds)
            ->delete();

        return $created;
    }

    /**
     * Insert or update a single transaction with the given settlement status.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function upsertTransaction(BankAccount $account, array $transaction, string $status): BankTransaction
    {
        return $account->transactions()->updateOrCreate([
            'truelayer_transaction_id' => $transaction['transaction_id'],
        ], [
            'user_id' => $account->user_id,
            'status' => $status,
            'normalised_provider_transaction_id' => $transaction['normalised_provider_transaction_id'] ?? null,
            'provider_transaction_id' => $transaction['provider_transaction_id'] ?? null,
            'booked_at' => isset($transaction['timestamp']) ? Carbon::parse($transaction['timestamp']) : null,
            'description' => $transaction['description'] ?? null,
            'amount' => $this->signedAmount($transaction),
            'currency' => $transaction['currency'] ?? null,
            'transaction_type' => $transaction['transaction_type'] ?? null,
            'transaction_category' => $transaction['transaction_category'] ?? null,
            'transaction_classification' => $transaction['transaction_classification'] ?? null,
            'merchant_name' => $transaction['merchant_name'] ?? null,
            'running_balance' => Arr::get($transaction, 'running_balance.amount'),
            'running_balance_currency' => Arr::get($transaction, 'running_balance.currency'),
            'meta' => $transaction['meta'] ?? null,
            'raw' => $transaction,
        ]);
    }

    /**
     * Push a OneSignal cashback notification for each new spend.
     *
     * The recipient is decided in-app: only the configured user, and only when
     * they have push notifications enabled. OneSignal broadcasts to subscribers.
     *
     * @param  array<int, BankTransaction>  $newTransactions
     */
    private function notifyNewTransactions(BankConnection $connection, array $newTransactions): void
    {
        $user = $connection->user;

        if (! $user->push_notifications || $user->email !== config('services.onesignal.notify_email')) {
            return;
        }

        if (! $this->oneSignal->isConfigured()) {
            return;
        }

        // The cashback angle only applies to spending (debits).
        $spends = array_values(array_filter(
            $newTransactions,
            fn (BankTransaction $transaction) => (float) $transaction->amount < 0,
        ));

        // Oldest first so notifications arrive in chronological order.
        usort($spends, fn (BankTransaction $a, BankTransaction $b) => ($a->booked_at?->getTimestamp() ?? 0) <=> ($b->booked_at?->getTimestamp() ?? 0));

        foreach ($spends as $transaction) {
            $this->pushCashbackAlert($connection, $transaction);
        }
    }

    /**
     * Send a single cashback push for one spend.
     */
    private function pushCashbackAlert(BankConnection $connection, BankTransaction $transaction): void
    {
        $spent = $this->formatAmount(abs((float) $transaction->amount), $transaction->currency);
        $merchant = $transaction->merchant_name ?? $transaction->description ?? 'a merchant';
        $missed = $this->formatAmount($this->randomMissedSaving((float) $transaction->amount), $transaction->currency);

        $message = "You spent {$spent} at {$merchant}, you missed out on a saving of {$missed} by not paying through Mambo ❌";

        try {
            $this->oneSignal->send('Missed cashback 💸', $message, [
                'connection_id' => $connection->id,
                'transaction_id' => $transaction->id,
            ], $this->merchantLogoUrl($transaction));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Best-effort merchant logo URL resolved from the merchant name.
     *
     * TrueLayer does not supply merchant logos, so this guesses a domain and
     * uses the configured logo service. Returns null when nothing is resolvable.
     */
    private function merchantLogoUrl(BankTransaction $transaction): ?string
    {
        $template = config('services.onesignal.merchant_logo_template');

        if (! filled($template)) {
            return null;
        }

        $merchant = $transaction->merchant_name
            ?? data_get($transaction->meta, 'provider_merchant_name')
            ?? $transaction->description;

        $slug = preg_replace('/[^a-z0-9]/', '', strtolower((string) $merchant));

        if (! filled($slug)) {
            return null;
        }

        return str_replace(
            ['{domain}', '{merchant}'],
            ["{$slug}.com", rawurlencode((string) $merchant)],
            $template,
        );
    }

    /**
     * A demo "missed saving" amount, randomised up to roughly half the spend.
     */
    private function randomMissedSaving(float $amount): float
    {
        $spend = abs($amount);
        $maxPence = max(100, (int) round($spend * 100 * 0.5));

        return random_int(50, $maxPence) / 100;
    }

    /**
     * Format a positive amount with its currency symbol, e.g. "£5.80".
     */
    private function formatAmount(float $amount, ?string $currency): string
    {
        $symbol = ['GBP' => '£', 'EUR' => '€', 'USD' => '$'][$currency ?? 'GBP'] ?? '';

        return $symbol.number_format($amount, 2);
    }

    /**
     * Normalise the amount so debits are always negative and credits positive.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function signedAmount(array $transaction): ?float
    {
        if (! isset($transaction['amount'])) {
            return null;
        }

        $amount = abs((float) $transaction['amount']);

        return ($transaction['transaction_type'] ?? null) === 'DEBIT' ? -$amount : $amount;
    }

    /**
     * Return a valid access token, refreshing it via the refresh token when expired.
     *
     * @throws RuntimeException
     */
    private function freshAccessToken(BankConnection $connection): string
    {
        if (! $connection->tokenHasExpired()) {
            return $connection->access_token;
        }

        if (! $connection->refresh_token) {
            throw new RuntimeException("Connection [{$connection->id}] has no refresh token to renew offline access.");
        }

        $tokens = $this->trueLayer->refreshToken($connection->refresh_token);

        $connection->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => isset($tokens['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokens['expires_in'])
                : null,
        ]);

        return $connection->access_token;
    }
}
