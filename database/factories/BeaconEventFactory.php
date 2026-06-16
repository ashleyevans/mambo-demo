<?php

namespace Database\Factories;

use App\Models\BeaconEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BeaconEvent>
 */
class BeaconEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'major' => $this->faker->numberBetween(1, 50),
            'minor' => $this->faker->numberBetween(1, 200),
            'type' => $this->faker->randomElement(['enter', 'exit']),
        ];
    }
}
