<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Consultation;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
    }

    public function test_the_session_reports_the_real_role(): void
    {
        $this->actingAs($this->admin('support'), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->getJson('/api/admin/session')
            ->assertStatus(200)
            ->assertJsonPath('role', 'support')
            ->assertJsonPath('admin.role', 'support');
    }

    public function test_login_returns_the_role(): void
    {
        $this->admin('superadmin', 'super@example.com');

        $this->postJson('/api/admin/login', [
            'email' => 'super@example.com',
            'password' => 'secret-password',
        ])
            ->assertStatus(200)
            ->assertJsonPath('admin.role', 'superadmin');
    }

    public function test_support_can_read_but_not_write(): void
    {
        $this->actingAs($this->admin('support'), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->getJson('/api/admin/dashboard')->assertStatus(200);
        $this->getJson('/api/admin/users')->assertStatus(200);
        $this->getJson('/api/admin/projects')->assertStatus(200);
        $this->getJson('/api/admin/members')->assertStatus(200);

        $this->patchJson('/api/admin/projects/'.$this->seedProject().'/stage', ['stage' => 'client_liaison'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Role Anda tidak punya akses untuk aksi ini.']);
    }

    public function test_admin_runs_operations_but_cannot_manage_clients_or_members(): void
    {
        $project = $this->seedProject();

        $this->actingAs($this->admin('admin'), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'client_liaison'])
            ->assertStatus(200)
            ->assertJsonPath('project.status', 'reviewing');

        $this->deleteJson('/api/admin/users', ['ids' => ['whatever']])
            ->assertStatus(403)
            ->assertJson(['message' => 'Role Anda tidak punya akses untuk aksi ini.']);

        $this->postJson('/api/admin/members', ['emails' => 'baru@contoh.com'])
            ->assertStatus(403);

        $this->deleteJson('/api/admin/members', ['emails' => ['baru@contoh.com']])
            ->assertStatus(403);

        $this->getJson('/api/admin/members')->assertStatus(200);
    }

    public function test_superadmin_can_manage_clients_and_the_whitelist(): void
    {
        $this->actingAs($this->admin('superadmin'), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->postJson('/api/admin/members', ['emails' => 'vip@contoh.com'])
            ->assertStatus(200)
            ->assertJsonPath('added', ['vip@contoh.com']);

        $this->deleteJson('/api/admin/users', [])
            ->assertStatus(400)
            ->assertJson(['message' => 'Pilih minimal satu akun.']);
    }

    private function seedProject(): string
    {
        $service = Service::query()->where('slug', 'basic')->firstOrFail();

        $consultation = Consultation::query()->create([
            'user_id' => User::factory()->create()->id,
            'service_id' => $service->id,
            'title' => 'Project uji role',
            'description' => 'Dipakai untuk menguji pembagian role admin.',
            'status' => 'submitted',
        ]);

        $consultation->update(['workflow_stage' => 'client_intake']);

        return (string) $consultation->id;
    }

    private function admin(string $role = 'admin', string $email = 'admin@example.com'): Admin
    {
        return Admin::query()->create([
            'email' => $email,
            'password_hash' => Hash::make('secret-password'),
            'name' => 'WRBN Admin',
            'role' => $role,
        ]);
    }
}
