<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['DEBIT', 'CREDIT']);
        $amount = $this->faker->randomFloat(2, 1, 500);
        $signed = $type === 'DEBIT' ? -$amount : $amount;
        $id = $this->faker->uuid();

        return [
            'bank_account_id' => BankAccount::factory(),
            'user_id' => fn (array $attributes) => BankAccount::find($attributes['bank_account_id'])->user_id,
            'truelayer_transaction_id' => $id,
            'normalised_provider_transaction_id' => 'np-'.$this->faker->numerify('########'),
            'provider_transaction_id' => 'p-'.$this->faker->numerify('########'),
            'booked_at' => $this->faker->dateTimeBetween('-60 days'),
            'description' => $this->faker->company(),
            'amount' => $signed,
            'currency' => 'GBP',
            'transaction_type' => $type,
            'transaction_category' => $this->faker->randomElement(['PURCHASE', 'TRANSFER', 'DIRECT_DEBIT']),
            'transaction_classification' => ['Shopping', 'General'],
            'merchant_name' => $this->faker->company(),
            'running_balance' => $this->faker->randomFloat(2, 0, 10000),
            'running_balance_currency' => 'GBP',
            'meta' => ['provider_reference' => $this->faker->bothify('REF-####')],
            'raw' => ['transaction_id' => $id, 'amount' => $signed, 'transaction_type' => $type],
        ];
    }
}
