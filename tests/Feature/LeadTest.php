<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;

it('denies a rep from seeing another rep lead', function () {
    $rep1 = User::factory()->rep()->create();
    $rep2 = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep2)->create();

    $response = $this->actingAs($rep1, 'sanctum')
        ->getJson("/api/leads/{$lead->id}");

    $response->assertStatus(403);
});

it('allows a manager to see any lead', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create();

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson("/api/leads/{$lead->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $lead->id);
});

it('allows a rep to see their own assigned lead', function () {
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep)->create();

    $response = $this->actingAs($rep, 'sanctum')
        ->getJson("/api/leads/{$lead->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $lead->id);
});

it('filters leads by status, source, and assigned_to', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->rep()->create();

    Lead::factory()->create(['status' => LeadStatus::New, 'source' => LeadSource::Web]);
    Lead::factory()->create(['status' => LeadStatus::Contacted, 'source' => LeadSource::Web]);
    Lead::factory()->assignedTo($rep)->create(['status' => LeadStatus::New, 'source' => LeadSource::Referral]);

    // Filter by status
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?status=new');
    $response->assertOk()->assertJsonCount(2, 'data');

    // Filter by source
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?source=web');
    $response->assertOk()->assertJsonCount(2, 'data');

    // Filter by assigned_to
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson("/api/leads?assigned_to={$rep->id}");
    $response->assertOk()->assertJsonCount(1, 'data');
});

it('searches leads by name, email, and company', function () {
    $manager = User::factory()->manager()->create();

    Lead::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com', 'company' => 'Google']);
    Lead::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'company' => 'Apple']);

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?search=john');
    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'John Doe');

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?search=apple');
    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Jane Smith');
});

it('paginates leads list', function () {
    $manager = User::factory()->manager()->create();
    Lead::factory()->count(20)->create();

    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?per_page=5');

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
        ])
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.total', 20);
});

it('sorts leads by expected_value and created_at', function () {
    $manager = User::factory()->manager()->create();

    $lead1 = Lead::factory()->create(['expected_value' => 1000.00, 'created_at' => now()->subDays(2)]);
    $lead2 = Lead::factory()->create(['expected_value' => 5000.00, 'created_at' => now()->subDay()]);
    $lead3 = Lead::factory()->create(['expected_value' => 2500.00, 'created_at' => now()]);

    // Sort by expected_value asc
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?sort=expected_value&direction=asc');
    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($lead1->id)
        ->and($response->json('data.1.id'))->toBe($lead3->id)
        ->and($response->json('data.2.id'))->toBe($lead2->id);

    // Sort by created_at desc
    $response = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/leads?sort=created_at&direction=desc');
    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($lead3->id)
        ->and($response->json('data.1.id'))->toBe($lead2->id)
        ->and($response->json('data.2.id'))->toBe($lead1->id);
});

it('validates required fields when creating a lead', function () {
    $manager = User::factory()->manager()->create();

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson('/api/leads', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'source']);
});

it('creates a lead successfully', function () {
    $manager = User::factory()->manager()->create();

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson('/api/leads', [
            'name' => 'Alice Cooper',
            'email' => 'alice@example.com',
            'phone' => '1234567890',
            'source' => 'web',
            'expected_value' => 15000.00,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Alice Cooper')
        ->assertJsonPath('data.status', 'new');
});

it('rejects status transition to won or lost without activities', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson("/api/leads/{$lead->id}", [
            'status' => 'won',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'A lead must have at least one logged activity before it can be marked as won or lost.');
});

it('allows status transition to won or lost with activities present', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    Activity::factory()->create(['lead_id' => $lead->id, 'user_id' => $manager->id]);

    $response = $this->actingAs($manager, 'sanctum')
        ->patchJson("/api/leads/{$lead->id}", [
            'status' => 'won',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'won');
});
