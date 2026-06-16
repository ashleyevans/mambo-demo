<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_connection_id' => BankConnection::factory(),
            'user_id' => fn (array $attributes) => BankConnection::find($attributes['bank_connection_id'])->user_id,
            'truelayer_account_id' => $this->faker->uuid(),
            'display_name' => $this->faker->randomElement(['Current Account', 'Savings Account', 'Everyday Account']),
            'account_type' => $this->faker->randomElement(['TRANSACTION', 'SAVINGS']),
            'currency' => 'GBP',
            'account_number' => (string) $this->faker->numberBetween(10000000, 99999999),
            'sort_code' => $this->faker->numerify('##-##-##'),
            'iban' => null,
            'current_balance' => $this->faker->randomFloat(2, 0, 25000),
            'available_balance' => $this->faker->randomFloat(2, 0, 25000),
            'last_synced_at' => now(),
        ];
    }
}
