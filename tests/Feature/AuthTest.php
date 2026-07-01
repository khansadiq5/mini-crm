<?php

use App\Models\User;

it('returns a token on successful login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ],
        ])
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', $user->email);
});

it('returns 422 with wrong password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 with non-existent email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when email or password is missing', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    $this->postJson('/api/login', ['email' => 'test@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns authenticated user on GET /api/me', function () {
    $user = User::factory()->manager()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'manager');
});

it('returns 401 for unauthenticated request to /api/me', function () {
    $response = $this->getJson('/api/me');

    $response->assertUnauthorized();
});

it('can logout and revoke token', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    // Login first
    $loginResponse = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $token = $loginResponse->json('data.token');

    // Verify token exists
    expect($user->tokens()->count())->toBe(1);

    // Logout
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('data.message', 'Logged out successfully.');

    // Verify the token was deleted from the database
    expect($user->fresh()->tokens()->count())->toBe(0);
});
