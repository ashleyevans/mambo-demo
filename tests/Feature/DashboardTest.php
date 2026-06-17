<?php

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard lists transactions newest first with bank details', function () {
    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->create([
        'provider_name' => 'Mock Bank',
        'logo_uri' => 'https://logo.test/mock.png',
    ]);
    $account = BankAccount::factory()->for($connection, 'connection')->create(['user_id' => $user->id]);

    $older = BankTransaction::factory()->for($account, 'account')->create([
        'user_id' => $user->id,
        'booked_at' => now()->subDays(5),
        'description' => 'Older transaction',
    ]);
    $newer = BankTransaction::factory()->for($account, 'account')->create([
        'user_id' => $user->id,
        'booked_at' => now()->subDay(),
        'description' => 'Newer transaction',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('transactionCount', 2)
            ->has('transactions', 2)
            ->where('transactions.0.id', $newer->id)
            ->where('transactions.1.id', $older->id)
            ->where('transactions.0.bank.name', 'Mock Bank')
            ->where('transactions.0.bank.logo', 'https://logo.test/mock.png')
        );
});

test('only the users own transactions are shown', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    BankTransaction::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('transactionCount', 0));
});

test('transactions can be exported as csv', function () {
    $user = User::factory()->create();
    BankTransaction::factory()->create([
        'user_id' => $user->id,
        'truelayer_transaction_id' => 'tx-csv',
        'merchant_name' => 'Acme Ltd',
    ]);

    $response = $this->actingAs($user)->get(route('transactions.export.csv'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())
        ->toContain('truelayer_transaction_id')
        ->toContain('tx-csv')
        ->toContain('Acme Ltd');
});

test('transactions can be exported as json with the raw payload', function () {
    $user = User::factory()->create();
    BankTransaction::factory()->create([
        'user_id' => $user->id,
        'raw' => ['transaction_id' => 'tx-json', 'foo' => 'bar'],
    ]);

    $response = $this->actingAs($user)->get(route('transactions.export.json'));

    $response->assertOk();
    $payload = json_decode($response->streamedContent(), true);
    expect($payload)->toHaveCount(1);
    expect($payload[0]['transaction']['transaction_id'])->toBe('tx-json');
    expect($payload[0]['transaction']['foo'])->toBe('bar');
});

test('exports require authentication', function () {
    $this->get(route('transactions.export.csv'))->assertRedirect(route('login'));
    $this->get(route('transactions.export.json'))->assertRedirect(route('login'));
});

test('a single transaction can be exported as csv and json', function () {
    $user = User::factory()->create();
    $transaction = BankTransaction::factory()->create([
        'user_id' => $user->id,
        'truelayer_transaction_id' => 'tx-one',
        'raw' => ['transaction_id' => 'tx-one', 'amount' => -9.99],
    ]);

    $csv = $this->actingAs($user)->get(route('transactions.export.single.csv', $transaction));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('tx-one');

    $jsonResponse = $this->actingAs($user)->get(route('transactions.export.single.json', $transaction));
    $jsonResponse->assertOk();
    $payload = json_decode($jsonResponse->streamedContent(), true);
    expect($payload['transaction']['transaction_id'])->toBe('tx-one');
});

test('a user cannot export another users transaction', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $transaction = BankTransaction::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->get(route('transactions.export.single.csv', $transaction))
        ->assertForbidden();
    $this->actingAs($user)
        ->get(route('transactions.export.single.json', $transaction))
        ->assertForbidden();
});

test('the dashboard exposes the demo refresh setting', function () {
    $user = User::factory()->create(['demo_refresh' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('demoRefresh', true));
});

test('a user can toggle demo refresh on and off', function () {
    $user = User::factory()->create(['demo_refresh' => false]);

    $this->actingAs($user)
        ->patch(route('dashboard.demo-refresh'), ['demo_refresh' => true])
        ->assertRedirect();
    expect($user->fresh()->demo_refresh)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('dashboard.demo-refresh'), ['demo_refresh' => false])
        ->assertRedirect();
    expect($user->fresh()->demo_refresh)->toBeFalse();
});

test('enabling demo refresh captures the request IP onto bank connections', function () {
    $user = User::factory()->create(['demo_refresh' => false]);
    $connection = BankConnection::factory()->for($user)->create(['psu_ip' => null]);

    $this->actingAs($user)
        ->patch(route('dashboard.demo-refresh'), ['demo_refresh' => true], ['REMOTE_ADDR' => '198.51.100.22'])
        ->assertRedirect();

    expect($connection->fresh()->psu_ip)->toBe('198.51.100.22');
});

test('disabling demo refresh leaves the stored IP untouched', function () {
    $user = User::factory()->create(['demo_refresh' => true]);
    $connection = BankConnection::factory()->for($user)->create(['psu_ip' => '203.0.113.7']);

    $this->actingAs($user)
        ->patch(route('dashboard.demo-refresh'), ['demo_refresh' => false], ['REMOTE_ADDR' => '198.51.100.22'])
        ->assertRedirect();

    expect($connection->fresh()->psu_ip)->toBe('203.0.113.7');
});

test('the sync endpoint pulls fresh data for the users connections', function () {
    config()->set('services.truelayer.client_id', 'test-client');
    config()->set('services.truelayer.client_secret', 'test-secret');
    config()->set('services.truelayer.environment', 'sandbox');

    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->tokenExpired()->create([
        'refresh_token' => 'r1',
    ]);

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-sync',
                'display_name' => 'Synced Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '12345678'],
                'provider' => ['display_name' => 'Mock Bank'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 10.00, 'available' => 10.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response([
            'results' => [[
                'transaction_id' => 'tx-sync',
                'timestamp' => '2026-06-15T09:00:00Z',
                'description' => 'Demo refresh tx',
                'amount' => -2.00,
                'currency' => 'GBP',
                'transaction_type' => 'DEBIT',
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.sync'))
        ->assertRedirect();

    $this->assertDatabaseHas('bank_transactions', [
        'user_id' => $user->id,
        'truelayer_transaction_id' => 'tx-sync',
    ]);
});

test('demo refresh endpoints require authentication', function () {
    $this->patch(route('dashboard.demo-refresh'), ['demo_refresh' => true])
        ->assertRedirect(route('login'));
    $this->post(route('dashboard.sync'))->assertRedirect(route('login'));
});

test('the push toggle is only manageable by the configured user', function () {
    config()->set('services.onesignal.notify_email', 'ash@cellcastonline.com');

    $ash = User::factory()->create(['email' => 'ash@cellcastonline.com']);
    $other = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($ash)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('canManagePush', true));

    $this->actingAs($other)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('canManagePush', false));
});

test('the configured user can toggle push notifications', function () {
    config()->set('services.onesignal.notify_email', 'ash@cellcastonline.com');
    $ash = User::factory()->create(['email' => 'ash@cellcastonline.com', 'push_notifications' => false]);

    $this->actingAs($ash)
        ->patch(route('dashboard.push-notifications'), ['push_notifications' => true])
        ->assertRedirect();

    expect($ash->fresh()->push_notifications)->toBeTrue();
});

test('a different user cannot toggle push notifications', function () {
    config()->set('services.onesignal.notify_email', 'ash@cellcastonline.com');
    $other = User::factory()->create(['email' => 'someone@example.com', 'push_notifications' => false]);

    $this->actingAs($other)
        ->patch(route('dashboard.push-notifications'), ['push_notifications' => true])
        ->assertForbidden();

    expect($other->fresh()->push_notifications)->toBeFalse();
});
