<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_optics_user_can_login_fetch_profile_and_logout(): void
    {
        User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@luminaoptics.id',
            'phone' => '0813-9876-5432',
            'password' => 'password123',
            'role' => 'customer',
            'membership_tier' => 'Gold',
            'prescription_od' => '-1.25 / Cyl -0.50',
            'prescription_os' => '-1.50 / Cyl -0.25',
            'last_exam_date' => '2026-08-20',
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'budi@luminaoptics.id',
            'password' => 'password123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'budi@luminaoptics.id')
            ->assertJsonPath('data.user.membership_tier', 'Gold')
            ->assertJsonPath('data.user.prescription_od', '-1.25 / Cyl -0.50');

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Budi Santoso');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_customer_can_register_via_api(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Nadia Kusuma',
            'email' => 'nadia@luminaoptics.id',
            'phone' => '0812-0000-1111',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.user.membership_tier', 'Silver');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::create([
            'name' => 'Siti Rahmawati',
            'email' => 'siti@luminaoptics.id',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'siti@luminaoptics.id',
            'password' => 'salahpassword',
        ])->assertUnauthorized();
    }
}
