<?php

use App\Actions\SyncBankConnection;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const ACCOUNTS_RESPONSE = [
    'results' => [[
        'account_id' => 'acc-1',
        'display_name' => 'Current Account',
        'currency' => 'GBP',
        'account_number' => ['number' => '12345678'],
        'provider' => ['display_name' => 'Mock Bank'],
    ]],
];

/**
 * Fake the Data API with a per-sync sequence of settled and pending results.
 *
 * @param  array<int, array<int, array<string, mixed>>>  $settledPerSync
 * @param  array<int, array<int, array<string, mixed>>>  $pendingPerSync
 */
function fakeSequence(array $settledPerSync, array $pendingPerSync): void
{
    config()->set('services.truelayer.environment', 'sandbox');

    $settled = Http::sequence();
    foreach ($settledPerSync as $results) {
        $settled->push(['results' => $results]);
    }

    $pending = Http::sequence();
    foreach ($pendingPerSync as $results) {
        $pending->push(['results' => $results]);
    }

    Http::fake([
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response(ACCOUNTS_RESPONSE),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 100.00, 'available' => 100.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => $pending,
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => $settled,
    ]);
}

function syncConnection(BankConnection $connection): void
{
    app(SyncBankConnection::class)($connection);
}

$pendingTx = [
    'transaction_id' => 'tx-pending',
    'timestamp' => '2026-06-16T12:00:00Z',
    'description' => 'Costa',
    'amount' => -4.50,
    'currency' => 'GBP',
    'transaction_type' => 'DEBIT',
    'merchant_name' => 'Costa',
];

test('pending transactions are stored with a pending status', function () use ($pendingTx) {
    $connection = BankConnection::factory()->create();
    fakeSequence([[]], [[$pendingTx]]);

    syncConnection($connection);

    $this->assertDatabaseHas('bank_transactions', [
        'truelayer_transaction_id' => 'tx-pending',
        'status' => 'pending',
    ]);
});

test('pending transactions that disappear are removed on the next sync', function () use ($pendingTx) {
    $connection = BankConnection::factory()->create();
    fakeSequence([[], []], [[$pendingTx], []]);

    syncConnection($connection);
    expect(BankTransaction::where('status', 'pending')->count())->toBe(1);

    syncConnection($connection);
    expect(BankTransaction::where('status', 'pending')->count())->toBe(0);
});

test('a pending transaction that settles under the same id flips to settled without duplicating', function () use ($pendingTx) {
    $connection = BankConnection::factory()->create();
    $settled = array_merge($pendingTx, ['transaction_category' => 'PURCHASE']);
    fakeSequence([[], [$settled]], [[$pendingTx], []]);

    syncConnection($connection);
    syncConnection($connection);

    expect(BankTransaction::count())->toBe(1);
    $this->assertDatabaseHas('bank_transactions', [
        'truelayer_transaction_id' => 'tx-pending',
        'status' => 'settled',
    ]);
});

test('a settled transaction is not deleted by the pending cleanup', function () use ($pendingTx) {
    $connection = BankConnection::factory()->create();
    $settled = [
        'transaction_id' => 'tx-settled',
        'timestamp' => '2026-06-10T09:00:00Z',
        'description' => 'Tesco',
        'amount' => -20.00,
        'currency' => 'GBP',
        'transaction_type' => 'DEBIT',
    ];
    fakeSequence([[$settled], [$settled]], [[$pendingTx], []]);

    syncConnection($connection);
    syncConnection($connection);

    expect(BankTransaction::count())->toBe(1);
    $this->assertDatabaseHas('bank_transactions', [
        'truelayer_transaction_id' => 'tx-settled',
        'status' => 'settled',
    ]);
});

test('a new pending spend triggers a push notification', function () use ($pendingTx) {
    config()->set('services.onesignal.app_id', 'os-app');
    config()->set('services.onesignal.rest_api_key', 'os-key');
    config()->set('services.onesignal.notify_email', 'ash@cellcastonline.com');
    config()->set('services.onesignal.merchant_logo_template', null);

    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);
    $connection = BankConnection::factory()->for($user)->create();

    config()->set('services.truelayer.environment', 'sandbox');
    Http::fake([
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response(ACCOUNTS_RESPONSE),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response(['results' => [['current' => 100.0, 'available' => 100.0]]]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => [$pendingTx]]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response(['results' => []]),
        'api.onesignal.com/notifications' => Http::response(['id' => 'n1']),
    ]);

    syncConnection($connection);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.onesignal.com/notifications'
        && str_contains($request->data()['contents']['en'], 'Costa'));
});
