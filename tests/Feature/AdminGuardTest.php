<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_endpoints_reject_a_client_session(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/admin/session')->assertStatus(401);
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    public function test_client_endpoints_reject_an_admin_session(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_admin_login_validates_credentials(): void
    {
        $this->admin('secret-password');

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertStatus(200)->assertJsonPath('admin.email', 'admin@example.com');
    }

    private function admin(string $password = 'secret-password'): Admin
    {
        return Admin::query()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make($password),
            'name' => 'WRBN Admin',
        ]);
    }
}
