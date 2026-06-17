<?php

use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeTrueLayerData(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/transactions/pending') => Http::response(['results' => []]),
            str_contains($url, '/transactions') => Http::response(['results' => []]),
            str_contains($url, '/balance') => Http::response([
                'results' => [['currency' => 'GBP', 'available' => 950.50, 'current' => 1000.00]],
            ]),
            str_contains($url, '/data/v1/accounts') => Http::response([
                'results' => [[
                    'account_id' => 'acc-1',
                    'display_name' => 'Everyday Account',
                    'currency' => 'GBP',
                    'provider' => ['display_name' => 'Mock Bank', 'provider_id' => 'mock'],
                ]],
            ]),
            default => Http::response([], 200),
        };
    });
}

test('demo sync forwards the stored PSU IP as customer-present', function () {
    fakeTrueLayerData();

    $user = User::factory()->create(['demo_refresh' => true]);
    BankConnection::factory()->for($user)->create(['psu_ip' => '203.0.113.7']);

    $this->artisan('accounts:sync', ['--demo' => true])->assertSuccessful();

    Http::assertSent(fn ($request) => $request->header('X-PSU-IP') === ['203.0.113.7']);
});

test('normal sync does not send a PSU IP', function () {
    fakeTrueLayerData();

    $user = User::factory()->create(['demo_refresh' => false]);
    BankConnection::factory()->for($user)->create(['psu_ip' => '203.0.113.7']);

    $this->artisan('accounts:sync')->assertSuccessful();

    Http::assertSent(fn ($request) => ! $request->hasHeader('X-PSU-IP'));
});

test('demo sync without a stored PSU IP sends no header', function () {
    fakeTrueLayerData();

    $user = User::factory()->create(['demo_refresh' => true]);
    BankConnection::factory()->for($user)->create(['psu_ip' => null]);

    $this->artisan('accounts:sync', ['--demo' => true])->assertSuccessful();

    Http::assertSent(fn ($request) => ! $request->hasHeader('X-PSU-IP'));
});
