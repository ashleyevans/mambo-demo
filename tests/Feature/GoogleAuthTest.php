<?php

use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('user is redirected to google', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))->assertRedirect();
});

test('a new user is created and logged in from the google callback', function () {
    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'google_id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ]);
});

test('an existing google user is updated and logged in', function () {
    $user = User::factory()->create([
        'google_id' => 'google-123',
        'name' => 'Old Name',
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'New Name',
        'email' => $user->email,
        'avatar' => 'https://example.com/new.png',
    ]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::where('google_id', 'google-123')->count())->toBe(1);
    expect($user->fresh()->name)->toBe('New Name');
});
