<?php

use App\Http\Controllers\BeaconController;
use App\Models\BeaconEvent;
use App\Models\BeaconVisit;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.onesignal.app_id', 'test-app-id');
    config()->set('services.onesignal.rest_api_key', 'test-rest-key');
    config()->set('app.url', 'https://mambo.test');
});

it('records a beacon enter event, opens a visit, and pushes the Starbucks offer', function () {
    Http::fake([
        'api.onesignal.com/*' => Http::response(['id' => 'notif-id'], 200),
    ]);

    $response = $this->postJson('/api/beacon/enter', [
        'major' => 100,
        'minor' => 42,
    ]);

    $response->assertOk()->assertJson([
        'event' => 'enter',
        'major' => 100,
        'minor' => 42,
    ]);

    $this->assertDatabaseHas('beacon_events', [
        'major' => 100,
        'minor' => 42,
        'type' => 'enter',
    ]);

    $this->assertDatabaseHas('beacon_visits', [
        'major' => 100,
        'minor' => 42,
        'exited_at' => null,
        'duration_seconds' => null,
    ]);

    Http::assertSent(function ($request) {
        $variant = collect(BeaconController::OFFER_VARIANTS)
            ->firstWhere('title', $request['headings']['en']);

        return $request->url() === 'https://api.onesignal.com/notifications'
            && $variant !== null
            && $request['subtitle']['en'] === $variant['subtitle']
            && $request['contents']['en'] === $variant['message']
            && $request['large_icon'] === 'https://static.winamillion.com/images/starbucks.png'
            && $request['data'] === ['major' => 100, 'minor' => 42];
    });
});

it('does not open a second visit when one is already in progress', function () {
    Http::fake();

    $this->postJson('/api/beacon/enter', ['major' => 100, 'minor' => 42])->assertOk();
    $this->postJson('/api/beacon/enter', ['major' => 100, 'minor' => 42])->assertOk();

    expect(BeaconVisit::where('major', 100)->where('minor', 42)->open()->count())->toBe(1);
});

it('records a beacon exit event without pushing a notification', function () {
    Http::fake();

    $response = $this->postJson('/api/beacon/exit', [
        'major' => 100,
        'minor' => 42,
    ]);

    $response->assertOk()->assertJson([
        'event' => 'exit',
        'major' => 100,
        'minor' => 42,
    ]);

    $this->assertDatabaseHas('beacon_events', [
        'major' => 100,
        'minor' => 42,
        'type' => 'exit',
    ]);

    Http::assertNothingSent();
});

it('closes the open visit with dwell time on exit', function () {
    Http::fake();
    $this->freezeTime();

    $visit = BeaconVisit::factory()->ongoing()->create([
        'major' => 5,
        'minor' => 9,
        'entered_at' => now()->subMinutes(35),
    ]);

    $this->postJson('/api/beacon/exit', ['major' => 5, 'minor' => 9])
        ->assertOk()
        ->assertJson(['duration_seconds' => 35 * 60]);

    expect($visit->fresh())
        ->exited_at->not->toBeNull()
        ->duration_seconds->toBe(35 * 60);
});

it('logs an exit with no open visit as a raw event without creating a visit', function () {
    Http::fake();

    $this->postJson('/api/beacon/exit', ['major' => 5, 'minor' => 9])
        ->assertOk()
        ->assertJson(['duration_seconds' => null]);

    // Still recorded in the raw event feed...
    $this->assertDatabaseHas('beacon_events', [
        'major' => 5,
        'minor' => 9,
        'type' => 'exit',
    ]);

    // ...but no visit is created or affected.
    expect(BeaconVisit::count())->toBe(0);
});

it('does not reopen or alter an already-closed visit', function () {
    Http::fake();

    $visit = BeaconVisit::factory()->create([
        'major' => 5,
        'minor' => 9,
        'duration_seconds' => 12 * 60,
    ]);

    $this->postJson('/api/beacon/exit', ['major' => 5, 'minor' => 9])
        ->assertOk()
        ->assertJson(['duration_seconds' => null]);

    expect($visit->fresh()->duration_seconds)->toBe(12 * 60);
});

it('closes the open visit for the right beacon only', function () {
    Http::fake();
    $this->freezeTime();

    BeaconVisit::factory()->ongoing()->create(['major' => 1, 'minor' => 1, 'entered_at' => now()->subMinutes(10)]);
    BeaconVisit::factory()->ongoing()->create(['major' => 2, 'minor' => 2, 'entered_at' => now()->subMinutes(20)]);

    $this->postJson('/api/beacon/exit', ['major' => 2, 'minor' => 2])
        ->assertOk()
        ->assertJson(['duration_seconds' => 20 * 60]);

    expect(BeaconVisit::where('major', 1)->open()->count())->toBe(1);
    expect(BeaconVisit::where('major', 2)->open()->count())->toBe(0);
});

it('requires major and minor to be integers', function () {
    $this->postJson('/api/beacon/enter', [
        'major' => 'not-an-int',
    ])->assertUnprocessable()->assertJsonValidationErrors(['major', 'minor']);

    $this->postJson('/api/beacon/exit', [
        'minor' => 'not-an-int',
    ])->assertUnprocessable()->assertJsonValidationErrors(['major', 'minor']);

    expect(BeaconEvent::count())->toBe(0);
    expect(BeaconVisit::count())->toBe(0);
});

test('guests cannot view the beacons dashboard', function () {
    $this->get(route('beacons.index'))->assertRedirect(route('login'));
});

test('the demo offer button sends a push without recording an event or visit', function () {
    Http::fake([
        'api.onesignal.com/*' => Http::response(['id' => 'notif-id'], 200),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('beacons.demo-offer'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(BeaconEvent::count())->toBe(0);
    expect(BeaconVisit::count())->toBe(0);

    Http::assertSent(function ($request) {
        $titles = array_column(BeaconController::OFFER_VARIANTS, 'title');

        return $request->url() === 'https://api.onesignal.com/notifications'
            && in_array($request['headings']['en'], $titles, true)
            && $request['data'] === ['major' => 1, 'minor' => 1];
    });
});

test('the demo offer flashes an error and sends nothing when OneSignal is unconfigured', function () {
    config()->set('services.onesignal.app_id', null);
    config()->set('services.onesignal.rest_api_key', null);
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('beacons.demo-offer'))
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('guests cannot send the demo offer', function () {
    $this->post(route('beacons.demo-offer'))->assertRedirect(route('login'));
});

test('the beacons dashboard lists recent events', function () {
    $user = User::factory()->create();
    BeaconEvent::factory()->create(['major' => 7, 'minor' => 3, 'type' => 'enter']);

    $this->actingAs($user)
        ->get(route('beacons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('beacons/index')
            ->has('events', 1)
            ->where('events.0.major', 7)
            ->where('events.0.minor', 3)
            ->where('events.0.type', 'enter')
            ->where('eventCount', 1),
        );
});

test('the beacons dashboard lists visits with dwell time', function () {
    $user = User::factory()->create();

    $completed = BeaconVisit::factory()->create(['major' => 7, 'minor' => 3, 'duration_seconds' => 25 * 60]);
    $ongoing = BeaconVisit::factory()->ongoing()->create(['major' => 9, 'minor' => 1]);

    $this->actingAs($user)
        ->get(route('beacons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('beacons/index')
            ->has('visits', 2)
            // Newest visit (highest id) sorts first.
            ->where('visits.0.major', $ongoing->major)
            ->where('visits.0.ongoing', true)
            ->where('visits.0.duration_seconds', null)
            ->where('visits.1.major', $completed->major)
            ->where('visits.1.ongoing', false)
            ->where('visits.1.duration_seconds', 25 * 60),
        );
});

test('the beacons dashboard resolves the store for a known beacon', function () {
    $user = User::factory()->create();

    BeaconVisit::factory()->create(['major' => 1, 'minor' => 1]);
    BeaconEvent::factory()->create(['major' => 1, 'minor' => 1, 'type' => 'enter']);

    $this->actingAs($user)
        ->get(route('beacons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('beacons/index')
            ->where('visits.0.store.name', 'Starbucks · Great Portland Street, London')
            ->where('visits.0.store.logo', fn (string $logo) => str_ends_with($logo, '/images/shops/starbucks-mark.svg'))
            ->where('events.0.store.name', 'Starbucks · Great Portland Street, London')
            ->where('events.0.store.logo', fn (string $logo) => str_ends_with($logo, '/images/shops/starbucks-mark.svg')),
        );
});

test('the beacons dashboard leaves unknown beacons without a store', function () {
    $user = User::factory()->create();

    BeaconVisit::factory()->create(['major' => 42, 'minor' => 99]);

    $this->actingAs($user)
        ->get(route('beacons.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('beacons/index')
            ->where('visits.0.store.name', null)
            ->where('visits.0.store.logo', null),
        );
});
