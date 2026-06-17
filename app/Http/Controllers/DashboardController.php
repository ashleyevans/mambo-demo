<?php

namespace App\Http\Controllers;

use App\Actions\SyncBankConnection;
use App\Models\BankTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The number of transactions shown in the dashboard table.
     */
    private const TABLE_LIMIT = 100;

    public function __construct(private readonly SyncBankConnection $syncConnection) {}

    /**
     * Display the dashboard with recent transactions from every connected account.
     */
    public function index(Request $request): Response
    {
        $query = BankTransaction::query()
            ->where('user_id', $request->user()->id)
            ->with('account.connection:id,provider_name,logo_uri')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('booked_at');

        $transactions = (clone $query)
            ->limit(self::TABLE_LIMIT)
            ->get()
            ->map(fn (BankTransaction $transaction) => $this->presentTransaction($transaction));

        return Inertia::render('dashboard', [
            'transactions' => $transactions,
            'transactionCount' => (clone $query)->count(),
            'demoRefresh' => $request->user()->demo_refresh,
            'pushNotifications' => $request->user()->push_notifications,
            'canManagePush' => $request->user()->email === config('services.onesignal.notify_email'),
        ]);
    }

    /**
     * Toggle per-minute demo refreshing for the current user.
     */
    public function toggleDemoRefresh(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'demo_refresh' => ['required', 'boolean'],
        ]);

        $request->user()->update(['demo_refresh' => $validated['demo_refresh']]);

        // Capture the present customer's IP so demo-mode background syncs can
        // forward it as X-PSU-IP, without needing to reconnect the banks.
        if ($validated['demo_refresh']) {
            $request->user()->bankConnections()->update(['psu_ip' => $request->ip()]);
        }

        return back();
    }

    /**
     * Toggle per-transaction push notifications for the current user.
     */
    public function togglePushNotifications(Request $request): RedirectResponse
    {
        abort_unless($request->user()->email === config('services.onesignal.notify_email'), 403);

        $validated = $request->validate([
            'push_notifications' => ['required', 'boolean'],
        ]);

        $request->user()->update(['push_notifications' => $validated['push_notifications']]);

        return back();
    }

    /**
     * Pull fresh data from TrueLayer for the user's connections (used by demo polling).
     */
    public function sync(Request $request): RedirectResponse
    {
        $connections = $request->user()->bankConnections()->syncable()->get();

        foreach ($connections as $connection) {
            try {
                ($this->syncConnection)($connection);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back();
    }

    /**
     * Shape a transaction for the dashboard table, exposing every available field.
     *
     * @return array<string, mixed>
     */
    private function presentTransaction(BankTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'status' => $transaction->status,
            'booked_at' => $transaction->booked_at?->toIso8601String(),
            'description' => $transaction->description,
            'merchant_name' => $transaction->merchant_name,
            'transaction_type' => $transaction->transaction_type,
            'transaction_category' => $transaction->transaction_category,
            'transaction_classification' => $transaction->transaction_classification,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'running_balance' => $transaction->running_balance,
            'running_balance_currency' => $transaction->running_balance_currency,
            'provider_transaction_id' => $transaction->provider_transaction_id,
            'normalised_provider_transaction_id' => $transaction->normalised_provider_transaction_id,
            'meta' => $transaction->meta,
            'raw' => $transaction->raw,
            'bank' => [
                'name' => $transaction->account?->connection?->provider_name,
                'logo' => $transaction->account?->connection?->logo_uri,
            ],
            'account' => $transaction->account?->display_name,
        ];
    }
}
