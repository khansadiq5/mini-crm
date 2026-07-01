<?php

use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('calculates rep performance metrics accurately', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->rep()->create(['name' => 'John Rep']);

    // Seed John's leads
    // 1 new lead with expected value 1000.00
    $lead1 = Lead::factory()->assignedTo($rep)->create([
        'status' => LeadStatus::New,
        'expected_value' => 1000.00,
    ]);

    // 1 won lead with expected value 5000.00
    $lead2 = Lead::factory()->assignedTo($rep)->create([
        'status' => LeadStatus::Won,
        'expected_value' => 5000.00,
    ]);

    // 1 lost lead with expected value 2500.00
    Lead::factory()->assignedTo($rep)->create([
        'status' => LeadStatus::Lost,
        'expected_value' => 2500.00,
    ]);

    // Add activities: 2 on lead 1, 1 on lead 2
    Activity::factory()->count(2)->create(['lead_id' => $lead1->id, 'user_id' => $rep->id]);
    Activity::factory()->create(['lead_id' => $lead2->id, 'user_id' => $rep->id]);

    // Execute report as Manager
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/reports/rep-performance');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment([
            'name' => 'John Rep',
            'total_leads' => 3,
            'status_counts' => [
                'new' => 1,
                'contacted' => 0,
                'qualified' => 0,
                'won' => 1,
                'lost' => 1,
            ],
            'total_expected_value' => '8500.00',
            'won_expected_value' => '5000.00',
            'total_activities' => 3,
        ]);
});

it('scopes report to own row for rep and shows all for manager', function () {
    $manager = User::factory()->manager()->create();
    $rep1 = User::factory()->rep()->create(['name' => 'Rep One']);
    $rep2 = User::factory()->rep()->create(['name' => 'Rep Two']);

    // Seed one lead for each so they show in stats
    Lead::factory()->assignedTo($rep1)->create();
    Lead::factory()->assignedTo($rep2)->create();

    // 1. Rep One sees only their own stats
    $response = $this->actingAs($rep1, 'sanctum')
        ->getJson('/api/reports/rep-performance');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rep One');

    // 2. Manager sees both reps
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/reports/rep-performance');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('executes a constant number of queries regardless of reps count', function () {
    $manager = User::factory()->manager()->create();

    // Seed 2 reps and some data
    $reps = User::factory()->count(2)->rep()->create();
    foreach ($reps as $rep) {
        $leads = Lead::factory()->count(2)->assignedTo($rep)->create();
        foreach ($leads as $lead) {
            Activity::factory()->count(2)->create(['lead_id' => $lead->id, 'user_id' => $rep->id]);
        }
    }

    // Measure queries with 2 reps
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($manager, 'sanctum')->getJson('/api/reports/rep-performance');
    $queryCountWithTwoReps = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Seed 5 more reps
    $moreReps = User::factory()->count(5)->rep()->create();
    foreach ($moreReps as $rep) {
        $leads = Lead::factory()->count(2)->assignedTo($rep)->create();
        foreach ($leads as $lead) {
            Activity::factory()->count(2)->create(['lead_id' => $lead->id, 'user_id' => $rep->id]);
        }
    }

    // Measure queries with 7 reps
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($manager, 'sanctum')->getJson('/api/reports/rep-performance');
    $queryCountWithSevenReps = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Assert that the number of queries is identical (constant query complexity O(1))
    expect($queryCountWithSevenReps)->toBe($queryCountWithTwoReps);
});
