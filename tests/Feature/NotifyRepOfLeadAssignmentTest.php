<?php

use App\Jobs\NotifyRepOfLeadAssignment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

it('dispatches NotifyRepOfLeadAssignment job when a lead is assigned', function () {
    Queue::fake();

    $manager = User::factory()->manager()->create();
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/leads/{$lead->id}/assign", [
            'rep_id' => $rep->id,
        ])
        ->assertOk();

    Queue::assertPushed(NotifyRepOfLeadAssignment::class, function ($job) use ($rep, $lead) {
        return $job->repId === $rep->id
            && $job->leadId === $lead->id;
    });
});

it('dispatches NotifyRepOfLeadAssignment job on reassignment', function () {
    Queue::fake();

    $manager = User::factory()->manager()->create();
    $rep1 = User::factory()->rep()->create();
    $rep2 = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep1)->create();

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/leads/{$lead->id}/assign", [
            'rep_id' => $rep2->id,
        ])
        ->assertOk();

    Queue::assertPushed(NotifyRepOfLeadAssignment::class, function ($job) use ($rep2, $lead) {
        return $job->repId === $rep2->id
            && $job->leadId === $lead->id;
    });
});

it('does not dispatch NotifyRepOfLeadAssignment job when assignment validation fails', function () {
    Queue::fake();

    $manager = User::factory()->manager()->create();
    $otherManager = User::factory()->manager()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/leads/{$lead->id}/assign", [
            'rep_id' => $otherManager->id,
        ])
        ->assertStatus(422);

    Queue::assertNotPushed(NotifyRepOfLeadAssignment::class);
});

it('logs the notification message when the job is handled', function () {
    $job = new NotifyRepOfLeadAssignment(repId: 42, leadId: 99);

    Log::shouldReceive('info')
        ->once()
        ->with('Rep 42 notified about lead 99');

    $job->handle();
});
