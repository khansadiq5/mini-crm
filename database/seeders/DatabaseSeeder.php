<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Manager
        User::factory()->manager()->create([
            'name' => 'Manager User',
            'email' => 'manager@minicrm.test',
            'password' => bcrypt('password'),
        ]);

        // 2. Seed 3 Reps
        $reps = collect([
            'rep1@minicrm.test',
            'rep2@minicrm.test',
            'rep3@minicrm.test',
        ])->map(function ($email, $index) {
            return User::factory()->rep()->create([
                'name' => 'Representative '.($index + 1),
                'email' => $email,
                'password' => bcrypt('password'),
            ]);
        });

        // 3. Seed 25 Leads & Activities
        foreach (range(1, 25) as $i) {
            $rep = rand(1, 100) <= 80 ? $reps->random() : null;
            $status = fake()->randomElement(LeadStatus::cases());

            $lead = Lead::factory()->create([
                'assigned_to' => $rep?->id,
                'status' => $status,
                'expected_value' => fake()->randomFloat(2, 1000, 75000),
            ]);

            // Seed activities: Mandatory for Won/Lost leads, 60% chance for others
            $needsActivities = in_array($status, [LeadStatus::Won, LeadStatus::Lost]);
            if ($needsActivities || rand(1, 100) <= 60) {
                // If the lead has no assigned rep, assign activity creator to the first rep
                $creator = $rep ?? $reps->first();

                Activity::factory()->count(rand(1, 4))->create([
                    'lead_id' => $lead->id,
                    'user_id' => $creator->id,
                ]);
            }
        }
    }
}
