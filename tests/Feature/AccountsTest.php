<?php

use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\TrueLayer;
use Illuminate\Support\Facades\Http;

function configureTrueLayer(): void
{
    config()->set('services.truelayer.client_id', 'test-client');
    config()->set('services.truelayer.client_secret', 'test-secret');
    config()->set('services.truelayer.redirect', 'https://mambo-demo.test/accounts/callback');
    config()->set('services.truelayer.environment', 'sandbox');
}

test('guests cannot view accounts', function () {
    $this->get(route('accounts.index'))->assertRedirect(route('login'));
});

test('the accounts page lists the connected accounts', function () {
    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->create(['provider_name' => 'Mock Bank']);
    BankAccount::factory()->for($connection, 'connection')->create([
        'user_id' => $user->id,
        'display_name' => 'Current Account',
    ]);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('connections', 1)
            ->where('connections.0.provider_name', 'Mock Bank')
            ->has('connections.0.accounts', 1)
            ->where('connections.0.accounts.0.display_name', 'Current Account')
        );
});

test('connect redirects to truelayer when configured', function () {
    configureTrueLayer();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('accounts.connect'));

    $response->assertRedirectContains('auth.truelayer-sandbox.com');
    expect(session('truelayer_state'))->not->toBeNull();
});

test('connect warns when truelayer is not configured', function () {
    config()->set('services.truelayer.client_id', null);
    config()->set('services.truelayer.client_secret', null);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.connect'))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');
});

test('the callback exchanges the code and stores accounts', function () {
    configureTrueLayer();
    $user = User::factory()->create();

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'access-123',
            'refresh_token' => 'refresh-123',
            'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-1',
                'account_type' => 'TRANSACTION',
                'display_name' => 'Everyday Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '12345678', 'sort_code' => '01-02-03'],
                'provider' => ['display_name' => 'Mock Bank', 'provider_id' => 'mock', 'logo_uri' => 'https://logo'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['currency' => 'GBP', 'available' => 950.50, 'current' => 1000.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response([
            'results' => [[
                'transaction_id' => 'tx-1',
                'timestamp' => '2026-06-10T12:00:00Z',
                'description' => 'COFFEE SHOP',
                'amount' => -3.50,
                'currency' => 'GBP',
                'transaction_type' => 'DEBIT',
                'transaction_category' => 'PURCHASE',
                'transaction_classification' => ['Food & Dining'],
                'merchant_name' => 'Coffee Shop',
                'running_balance' => ['amount' => 996.50, 'currency' => 'GBP'],
                'meta' => ['provider_transaction_category' => 'DEB'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['truelayer_state' => 'state-abc'])
        ->get(route('accounts.callback', ['code' => 'auth-code', 'state' => 'state-abc']));

    $response->assertRedirect(route('accounts.index'))->assertSessionHas('success');

    $this->assertDatabaseHas('bank_connections', [
        'user_id' => $user->id,
        'provider_name' => 'Mock Bank',
    ]);
    $this->assertDatabaseHas('bank_accounts', [
        'user_id' => $user->id,
        'truelayer_account_id' => 'acc-1',
        'display_name' => 'Everyday Account',
        'current_balance' => 1000.00,
        'available_balance' => 950.50,
        'sort_code' => '01-02-03',
    ]);
    $this->assertDatabaseHas('bank_transactions', [
        'user_id' => $user->id,
        'truelayer_transaction_id' => 'tx-1',
        'merchant_name' => 'Coffee Shop',
        'amount' => -3.50,
        'transaction_type' => 'DEBIT',
        'running_balance' => 996.50,
    ]);
});

test('the callback rejects a mismatched state', function () {
    configureTrueLayer();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['truelayer_state' => 'expected'])
        ->get(route('accounts.callback', ['code' => 'auth-code', 'state' => 'tampered']))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');

    expect(BankConnection::count())->toBe(0);
});

test('a user can disconnect a connection', function () {
    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->create();
    BankAccount::factory()->for($connection, 'connection')->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('accounts.connections.destroy', $connection))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('bank_connections', ['id' => $connection->id]);
    expect(BankAccount::count())->toBe(0);
});

test('a user cannot disconnect another users connection', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $connection = BankConnection::factory()->for($other)->create();

    $this->actingAs($user)
        ->delete(route('accounts.connections.destroy', $connection))
        ->assertForbidden();

    $this->assertDatabaseHas('bank_connections', ['id' => $connection->id]);
});

test('the callback stores a consent expiry for offline access', function () {
    configureTrueLayer();
    config()->set('services.truelayer.consent_days', 30);
    $user = User::factory()->create();

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'access-123',
            'refresh_token' => 'refresh-123',
            'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-1',
                'display_name' => 'Everyday Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '12345678', 'sort_code' => '01-02-03'],
                'provider' => ['display_name' => 'Mock Bank', 'provider_id' => 'mock'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 1000.00, 'available' => 950.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response(['results' => []]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $this->actingAs($user)
        ->withSession(['truelayer_state' => 'state-abc'])
        ->get(route('accounts.callback', ['code' => 'auth-code', 'state' => 'state-abc']));

    $connection = BankConnection::first();
    expect($connection->consent_expires_at->isAfter(now()->addDays(29)))->toBeTrue();
    expect($connection->refresh_token)->toBe('refresh-123');
});

test('refreshing renews an expired token and re-syncs balances', function () {
    configureTrueLayer();
    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->tokenExpired()->create([
        'refresh_token' => 'old-refresh',
    ]);

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-9',
                'display_name' => 'Savings',
                'currency' => 'GBP',
                'account_number' => ['number' => '99999999', 'sort_code' => '04-05-06'],
                'provider' => ['display_name' => 'Mock Bank'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 500.00, 'available' => 500.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response(['results' => []]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $this->actingAs($user)
        ->post(route('accounts.connections.refresh', $connection))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('success');

    $connection->refresh();
    expect($connection->refresh_token)->toBe('new-refresh');
    expect($connection->token_expires_at->isFuture())->toBeTrue();
    $this->assertDatabaseHas('bank_accounts', [
        'truelayer_account_id' => 'acc-9',
        'current_balance' => 500.00,
    ]);
});

test('refreshing is blocked once consent has expired', function () {
    $user = User::factory()->create();
    $connection = BankConnection::factory()->for($user)->consentExpired()->create();
    Http::fake();

    $this->actingAs($user)
        ->post(route('accounts.connections.refresh', $connection))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('the sync command only processes syncable connections', function () {
    configureTrueLayer();
    $active = BankConnection::factory()->tokenExpired()->create(['refresh_token' => 'r1']);
    BankConnection::factory()->consentExpired()->create(['refresh_token' => 'r2']);

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-cmd',
                'display_name' => 'Cmd Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '11112222'],
                'provider' => ['display_name' => 'Mock Bank'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 1.00, 'available' => 1.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response(['results' => []]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $this->artisan('accounts:sync')->assertSuccessful();

    $this->assertDatabaseHas('bank_accounts', [
        'bank_connection_id' => $active->id,
        'truelayer_account_id' => 'acc-cmd',
    ]);
    expect(BankAccount::count())->toBe(1);
});

test('a Failed results status raises so the sync can report it', function () {
    configureTrueLayer();

    Http::fake([
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response([
            'status' => 'Failed',
            'results' => [],
        ]),
    ]);

    expect(fn () => app(TrueLayer::class)->transactions('token', 'acc-1'))
        ->toThrow(RuntimeException::class);
});

test('a Succeeded results status returns the results array', function () {
    configureTrueLayer();

    Http::fake([
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response([
            'status' => 'Succeeded',
            'results' => [['transaction_id' => 'tx-1'], ['transaction_id' => 'tx-2']],
        ]),
    ]);

    expect(app(TrueLayer::class)->transactions('token', 'acc-1'))->toHaveCount(2);
});

test('the demo sync command only processes demo enabled users', function () {
    configureTrueLayer();
    $demoUser = User::factory()->create(['demo_refresh' => true]);
    $regularUser = User::factory()->create(['demo_refresh' => false]);
    $demoConnection = BankConnection::factory()->for($demoUser)->tokenExpired()->create(['refresh_token' => 'r1']);
    BankConnection::factory()->for($regularUser)->tokenExpired()->create(['refresh_token' => 'r2']);

    Http::fake([
        'auth.truelayer-sandbox.com/connect/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 3600,
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts' => Http::response([
            'results' => [[
                'account_id' => 'acc-demo',
                'display_name' => 'Demo Account',
                'currency' => 'GBP',
                'account_number' => ['number' => '33334444'],
                'provider' => ['display_name' => 'Mock Bank'],
            ]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/balance' => Http::response([
            'results' => [['current' => 1.00, 'available' => 1.00]],
        ]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions' => Http::response(['results' => []]),
        'api.truelayer-sandbox.com/data/v1/accounts/*/transactions/pending' => Http::response(['results' => []]),
    ]);

    $this->artisan('accounts:sync --demo')->assertSuccessful();

    expect(BankAccount::count())->toBe(1);
    $this->assertDatabaseHas('bank_accounts', [
        'bank_connection_id' => $demoConnection->id,
        'truelayer_account_id' => 'acc-demo',
    ]);
});
