<?php

use App\Actions\SyncBankConnection;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function configureNotifications(): void
{
    config()->set('services.truelayer.client_id', 'test-client');
    config()->set('services.truelayer.client_secret', 'test-secret');
    config()->set('services.truelayer.environment', 'sandbox');
    config()->set('services.onesignal.app_id', 'os-app');
    config()->set('services.onesignal.rest_api_key', 'os-key');
    config()->set('services.onesignal.notify_email', 'ash@cellcastonline.com');
    config()->set('services.onesignal.segment', 'Subscribed Users');
    config()->set('services.onesignal.merchant_logo_template', 'https://logos.test/{domain}');
}

/**
 * @param  array<int, array<string, mixed>>  $transactions
 */
function fakeTrueLayerWith(array $transactions): void
{
    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-1',
                'display_name' => 'Current Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '12345678'],
                'provider' => ['display_name' => 'Mock Bank'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 100.00, 'available' => 100.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response([
            'results' => $transactions,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
        'api.onesignal.com/notifications' => Http::response(['id' => 'notif-1']),
    ]);
}

function syncFor(User $user): void
{
    $connection = BankConnection::factory()->for($user)->tokenExpired()->create([
        'refresh_token' => 'r1',
    ]);

    app(SyncBankConnection::class)($connection);
}

function assertNoPush(): void
{
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.onesignal.com/notifications');
}

$spend = [
    'transaction_id' => 'tx-1',
    'timestamp' => '2026-06-16T10:00:00Z',
    'description' => 'STARBUCKS',
    'amount' => -5.80,
    'currency' => 'GBP',
    'transaction_type' => 'DEBIT',
    'merchant_name' => 'Starbucks',
];

test('a new spend pushes a cashback message and broadcasts to subscribers', function () use ($spend) {
    configureNotifications();
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.onesignal.com/notifications'
            && $body['app_id'] === 'os-app'
            && $body['included_segments'] === ['Subscribed Users']
            && str_contains($body['contents']['en'], 'You spent £5.80 at Starbucks')
            && str_contains($body['contents']['en'], 'by not paying through Mambo')
            && str_contains($body['contents']['en'], '❌');
    });
});

test('the cashback push includes a merchant logo image when resolvable', function () use ($spend) {
    configureNotifications();
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.onesignal.com/notifications'
            && ($body['large_icon'] ?? null) === 'https://logos.test/starbucks.com'
            && ($body['ios_attachments']['logo'] ?? null) === 'https://logos.test/starbucks.com'
            && ($body['chrome_web_icon'] ?? null) === 'https://logos.test/starbucks.com';
    });
});

test('no image is attached when the logo template is not configured', function () use ($spend) {
    configureNotifications();
    config()->set('services.onesignal.merchant_logo_template', null);
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.onesignal.com/notifications'
        && ! isset($request->data()['large_icon']));
});

test('no push is sent when push notifications are off', function () use ($spend) {
    configureNotifications();
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => false]);

    syncFor($user);

    assertNoPush();
});

test('a push is sent for every new spend, not just a summary', function () {
    configureNotifications();
    fakeTrueLayerWith([
        ['transaction_id' => 'tx-1', 'timestamp' => '2026-06-16T09:00:00Z', 'description' => 'Tesco', 'amount' => -10.00, 'currency' => 'GBP', 'transaction_type' => 'DEBIT', 'merchant_name' => 'Tesco'],
        ['transaction_id' => 'tx-2', 'timestamp' => '2026-06-16T10:00:00Z', 'description' => 'Greggs', 'amount' => -3.20, 'currency' => 'GBP', 'transaction_type' => 'DEBIT', 'merchant_name' => 'Greggs'],
        ['transaction_id' => 'tx-3', 'timestamp' => '2026-06-16T11:00:00Z', 'description' => 'Salary', 'amount' => 2000.00, 'currency' => 'GBP', 'transaction_type' => 'CREDIT'],
    ]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    // Two debits => two pushes; the credit is ignored.
    $pushes = Http::recorded(fn ($request) => $request->url() === 'https://api.onesignal.com/notifications');
    expect($pushes)->toHaveCount(2);
});

test('no push is sent for a different user', function () use ($spend) {
    configureNotifications();
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'someone@example.com', 'push_notifications' => true]);

    syncFor($user);

    assertNoPush();
});

test('no push is sent when onesignal is not configured', function () use ($spend) {
    configureNotifications();
    config()->set('services.onesignal.app_id', null);
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    assertNoPush();
});

test('credits do not trigger a cashback push', function () {
    configureNotifications();
    fakeTrueLayerWith([[
        'transaction_id' => 'tx-credit',
        'timestamp' => '2026-06-16T10:00:00Z',
        'description' => 'SALARY',
        'amount' => 2500.00,
        'currency' => 'GBP',
        'transaction_type' => 'CREDIT',
    ]]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    assertNoPush();
});

test('re-syncing existing transactions sends no push', function () use ($spend) {
    configureNotifications();
    fakeTrueLayerWith([$spend]);
    $user = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => true]);

    syncFor($user);

    // Reset recorded requests; the same transaction now already exists.
    fakeTrueLayerWith([$spend]);
    app(SyncBankConnection::class)($user->bankConnections()->first());

    assertNoPush();
});
