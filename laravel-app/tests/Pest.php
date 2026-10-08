<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
 * Feature tests run against a real PostgreSQL test database (sequences, timestamptz and ilike
 * behave like production), reset per test with RefreshDatabase.
 */
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Unit');

/** Headers for the legacy v1 API. */
function legacyHeaders(): array
{
    return ['X-Api-Key' => 'test-legacy-key', 'Accept' => 'application/json'];
}

function actingAsApiUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}
