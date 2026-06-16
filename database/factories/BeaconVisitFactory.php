<?php

namespace Database\Factories;

use App\Models\BeaconVisit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BeaconVisit>
 */
class BeaconVisitFactory extends Factory
{
    /**
     * Define the model's default state (a completed visit).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enteredAt = Carbon::parse($this->faker->dateTimeBetween('-30 days', '-1 hour'));
        $duration = $this->faker->numberBetween(60, 3600);

        return [
            'major' => $this->faker->numberBetween(1, 50),
            'minor' => $this->faker->numberBetween(1, 200),
            'entered_at' => $enteredAt,
            'exited_at' => (clone $enteredAt)->addSeconds($duration),
            'duration_seconds' => $duration,
        ];
    }

    /**
     * An in-progress visit with no exit recorded yet.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'exited_at' => null,
            'duration_seconds' => null,
        ]);
    }
}
