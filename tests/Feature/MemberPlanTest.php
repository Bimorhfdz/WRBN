<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MemberEmail;
use App\Models\Service;
use App\Models\User;
use App\Support\MemberPlan;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
        config(['wrbn.member_max_active' => 3]);
    }

    public function test_a_new_account_starts_on_the_general_plan(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'umum@example.com']), 'web');

        $this->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.access_plan', 'general');
    }

    public function test_whitelisting_an_address_upgrades_the_account_on_the_next_login(): void
    {
        $user = User::factory()->create(['email' => 'langganan@example.com']);

        $this->whitelist('langganan@example.com');
        MemberPlan::sync($user);

        $this->assertSame('member', $user->fresh()->access_plan);

        $this->postJson('/api/auth/login', [
            'email' => 'langganan@example.com',
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonPath('user.access_plan', 'member');
    }

    public function test_removing_an_address_returns_the_account_to_general_and_keeps_its_projects(): void
    {
        $user = $this->member('hilang@example.com');
        $projectId = $this->createProject($user);

        DB::table('member_emails')->where('email', 'hilang@example.com')->delete();

        $this->postJson('/api/auth/login', [
            'email' => 'hilang@example.com',
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonPath('user.access_plan', 'general');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('consultations', ['id' => $projectId]);
    }

    public function test_an_expired_whitelist_entry_does_not_grant_membership(): void
    {
        User::factory()->create(['email' => 'kadaluarsa@example.com']);
        $this->whitelist('kadaluarsa@example.com', now()->subDay()->toDateTimeString());

        $this->postJson('/api/auth/login', [
            'email' => 'kadaluarsa@example.com',
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonPath('user.access_plan', 'general');
    }

    public function test_a_member_can_create_a_project_without_a_paid_payment(): void
    {
        $this->actingAs($this->member('member@example.com'), 'web');

        $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('consultation.payment_id', null);
    }

    public function test_a_member_is_capped_at_the_configured_number_of_active_projects(): void
    {
        $this->actingAs($this->member('penuh@example.com'), 'web');

        foreach (['basic', 'vip', 'custom'] as $slug) {
            $this->postJson('/api/consultations', $this->payload($slug))->assertStatus(201);
        }

        $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(409)
            ->assertJson(['message' => 'Batas project aktif member tercapai. Selesaikan project berjalan terlebih dahulu.']);
    }

    public function test_a_general_account_still_needs_a_paid_payment(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(403)
            ->assertJson(['message' => 'Paket belum dibayar. Selesaikan payment terlebih dahulu.']);
    }

    public function test_admin_can_manage_the_whitelist(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->postJson('/api/admin/members', [
            'emails' => "satu@contoh.com,dua@contoh.com\n  tiga@contoh.com",
            'planLabel' => 'Paket Tahunan',
        ])
            ->assertStatus(200)
            ->assertJsonPath('added', ['satu@contoh.com', 'dua@contoh.com', 'tiga@contoh.com']);

        $this->assertCount(3, $this->getJson('/api/admin/members')->json('members'));

        $this->postJson('/api/admin/members', ['emails' => 'bukan-email'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Tidak ada email yang valid.']);

        $id = $this->getJson('/api/admin/members')->json('members.0.id');

        $this->deleteJson('/api/admin/members', ['ids' => [$id]])
            ->assertStatus(200)
            ->assertJsonPath('deleted', [$id]);

        $this->assertCount(2, $this->getJson('/api/admin/members')->json('members'));

        $this->deleteJson('/api/admin/members', ['emails' => ['dua@contoh.com', 'tiga@contoh.com']])
            ->assertStatus(200);

        $this->getJson('/api/admin/members')->assertStatus(200)->assertJsonPath('members', []);
    }

    private function whitelist(string $email, ?string $expiresAt = null): void
    {
        MemberEmail::query()->create([
            'email' => strtolower($email),
            'expires_at' => $expiresAt,
        ]);
    }

    private function member(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);

        $this->whitelist($email);

        return MemberPlan::sync($user);
    }

    private function createProject(User $user): string
    {
        $this->actingAs($user, 'web');

        return (string) $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(201)
            ->json('consultation.id');
    }

    private function payload(string $slug = 'basic'): array
    {
        $service = Service::query()->where('slug', $slug)->firstOrFail();

        return [
            'serviceId' => $service->id,
            'title' => 'Analisis penjualan',
            'description' => 'Analisis data penjualan tahunan dengan regresi.',
            'scheduledAt' => '2026-10-20 09:00',
            'notes' => 'Dataset menyusul',
        ];
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('secret-password'),
            'name' => 'WRBN Admin',
            'role' => 'superadmin',
        ]);
    }
}
