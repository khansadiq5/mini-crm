<?php

use App\Models\Lead;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| viewAny — any authenticated user
|--------------------------------------------------------------------------
*/

it('allows any authenticated user to viewAny leads', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->rep()->create();

    expect($manager->can('viewAny', Lead::class))->toBeTrue();
    expect($rep->can('viewAny', Lead::class))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| view — manager sees all, rep sees only assigned
|--------------------------------------------------------------------------
*/

it('allows a manager to view any lead', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create();

    expect($manager->can('view', $lead))->toBeTrue();
});

it('allows a rep to view a lead assigned to them', function () {
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep)->create();

    expect($rep->can('view', $lead))->toBeTrue();
});

it('denies a rep from viewing a lead assigned to someone else', function () {
    $rep = User::factory()->rep()->create();
    $otherRep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($otherRep)->create();

    expect($rep->can('view', $lead))->toBeFalse();
});

it('denies a rep from viewing an unassigned lead', function () {
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->create(); // assigned_to is null

    expect($rep->can('view', $lead))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| create — any authenticated user
|--------------------------------------------------------------------------
*/

it('allows any authenticated user to create a lead', function () {
    $manager = User::factory()->manager()->create();
    $rep = User::factory()->rep()->create();

    expect($manager->can('create', Lead::class))->toBeTrue();
    expect($rep->can('create', Lead::class))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| update — same rule as view
|--------------------------------------------------------------------------
*/

it('allows a manager to update any lead', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create();

    expect($manager->can('update', $lead))->toBeTrue();
});

it('allows a rep to update a lead assigned to them', function () {
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep)->create();

    expect($rep->can('update', $lead))->toBeTrue();
});

it('denies a rep from updating a lead assigned to someone else', function () {
    $rep = User::factory()->rep()->create();
    $otherRep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($otherRep)->create();

    expect($rep->can('update', $lead))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| assign — manager only
|--------------------------------------------------------------------------
*/

it('allows a manager to assign a lead', function () {
    $manager = User::factory()->manager()->create();
    $lead = Lead::factory()->create();

    expect($manager->can('assign', $lead))->toBeTrue();
});

it('denies a rep from assigning a lead', function () {
    $rep = User::factory()->rep()->create();
    $lead = Lead::factory()->assignedTo($rep)->create();

    expect($rep->can('assign', $lead))->toBeFalse();
});
