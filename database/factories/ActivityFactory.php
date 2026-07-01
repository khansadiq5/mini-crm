<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ActivityType::cases()),
            'body' => fake()->paragraph(),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Set the activity type to call.
     */
    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Call,
            'body' => 'Called to discuss '.fake()->bs().'.',
        ]);
    }

    /**
     * Set the activity type to email.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Email,
            'body' => 'Sent follow-up email regarding '.fake()->bs().'.',
        ]);
    }

    /**
     * Set the activity type to meeting.
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Meeting,
            'body' => 'Met to review '.fake()->bs().'.',
        ]);
    }

    /**
     * Set the activity type to note.
     */
    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ActivityType::Note,
            'body' => fake()->sentence(),
        ]);
    }
}
