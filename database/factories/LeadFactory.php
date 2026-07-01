<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'company' => fake()->optional(0.7)->company(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'status' => LeadStatus::New,
            'expected_value' => fake()->randomFloat(2, 500, 100000),
            'assigned_to' => null,
        ];
    }

    /**
     * Assign the lead to a specific user (rep).
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $user->id,
        ]);
    }

    /**
     * Set the lead status to contacted.
     */
    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Contacted,
        ]);
    }

    /**
     * Set the lead status to qualified.
     */
    public function qualified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Qualified,
        ]);
    }

    /**
     * Set the lead status to won.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Won,
        ]);
    }

    /**
     * Set the lead status to lost.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Lost,
        ]);
    }
}
