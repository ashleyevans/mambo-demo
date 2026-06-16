<?php

namespace Database\Factories;

use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankConnection>
 */
class BankConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider_id' => 'mock-'.$this->faker->word(),
            'provider_name' => $this->faker->company(),
            'logo_uri' => $this->faker->imageUrl(),
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'token_expires_at' => now()->addHour(),
            'consent_expires_at' => now()->addDays(90),
            'status' => 'active',
            'last_synced_at' => now(),
        ];
    }

    /**
     * Indicate that the connection's open banking consent has lapsed.
     */
    public function consentExpired(): static
    {
        return $this->state(fn () => ['consent_expires_at' => now()->subDay()]);
    }

    /**
     * Indicate that the stored access token has expired.
     */
    public function tokenExpired(): static
    {
        return $this->state(fn () => ['token_expires_at' => now()->subHour()]);
    }
}
