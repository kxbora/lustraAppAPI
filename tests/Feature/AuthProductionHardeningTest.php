<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $user = User::create([
            'name' => 'Auth User',
            'email' => 'auth.user@example.com',
            'password' => bcrypt('password123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.email', $user->email);
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(401);
        }

        $throttledResponse = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $throttledResponse->assertStatus(429);
    }
}
