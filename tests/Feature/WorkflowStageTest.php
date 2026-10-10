<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
    }

    public function test_admin_can_move_a_project_one_stage_forward(): void
    {
        $project = $this->project();
        $this->asAdmin();

        $this->patchJson("/api/admin/projects/{$project}/stage", [
            'stage' => 'client_liaison',
            'note' => 'Menunggu konfirmasi client.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('project.workflow_stage', 'client_liaison')
            ->assertJsonPath('project.status', 'reviewing');

        $this->assertDatabaseHas('consultations', [
            'id' => $project,
            'workflow_stage' => 'client_liaison',
            'status' => 'reviewing',
        ]);

        $this->assertDatabaseHas('stage_history', [
            'consultation_id' => $project,
            'from_stage' => 'client_intake',
            'to_stage' => 'client_liaison',
            'note' => 'Menunggu konfirmasi client.',
        ]);

        $this->assertDatabaseHas('project_updates', [
            'consultation_id' => $project,
            'status' => 'reviewing',
        ]);
    }

    public function test_a_stage_change_must_move_exactly_one_step(): void
    {
        $project = $this->project();
        $this->asAdmin();

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'koordinator'])
            ->assertStatus(409)
            ->assertJson(['message' => 'Perpindahan tahap harus satu langkah maju atau mundur.']);
    }

    public function test_a_stage_change_can_move_one_step_back(): void
    {
        $project = $this->project();
        $this->asAdmin();

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'client_liaison'])->assertStatus(200);

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'client_intake'])
            ->assertStatus(200)
            ->assertJsonPath('project.workflow_stage', 'client_intake')
            ->assertJsonPath('project.status', 'submitted');
    }

    public function test_an_unknown_stage_is_rejected(): void
    {
        $project = $this->project();
        $this->asAdmin();

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'quality_assurance'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Tahap tidak valid.']);
    }

    public function test_assignments_are_stored_and_synced_into_the_project(): void
    {
        $project = $this->project();
        $this->asAdmin();

        $this->putJson("/api/admin/projects/{$project}/assignments", [
            'assignments' => [
                ['role' => 'client_liaison', 'name' => 'Rina Client', 'contact' => '081111111111'],
                ['role' => 'analis_utama', 'name' => 'Budi Analis', 'contact' => '082222222222'],
                ['role' => 'qc', 'name' => 'Sari QC', 'contact' => '083333333333'],
            ],
        ])->assertStatus(200);

        $detail = $this->getJson("/api/admin/projects/{$project}")->assertStatus(200);

        $this->assertCount(3, $detail->json('assignments'));
        $this->assertSame('Budi Analis', $detail->json('project.consultant_name'));
        $this->assertSame('Rina Client', $detail->json('project.contact_person'));
        $this->assertSame('client_intake', $detail->json('workflow.current'));
        $this->assertContains('client_liaison', $detail->json('workflow.available'));
        $this->assertNotContains('koordinator', $detail->json('workflow.available'));

        $this->putJson("/api/admin/projects/{$project}/assignments", [
            'assignments' => [['role' => 'analis_utama', 'name' => 'Budi Analis', 'contact' => '082222222222']],
        ])->assertStatus(200);

        $this->assertCount(1, $this->getJson("/api/admin/projects/{$project}")->json('assignments'));
    }

    public function test_the_client_detail_exposes_the_stepper_and_the_liaison_contact(): void
    {
        $project = $this->project();

        $this->asAdmin();
        $this->putJson("/api/admin/projects/{$project}/assignments", [
            'assignments' => [
                ['role' => 'client_liaison', 'name' => 'Rina Client', 'contact' => '081111111111'],
            ],
        ])->assertStatus(200);

        $this->actingAs($this->owner, 'web');

        $detail = $this->getJson("/api/consultations/{$project}")->assertStatus(200);

        $this->assertCount(7, $detail->json('steps'));
        $this->assertSame('client_intake', $detail->json('currentStage'));
        $this->assertSame('Rina Client', $detail->json('clientContact.assignee_name'));
        $this->assertSame('Rina Client', $detail->json('consultation.contact_person'));
    }

    public function test_a_cancelled_project_cannot_change_stage(): void
    {
        $project = $this->project();

        $this->patchJson("/api/consultations/{$project}", ['status' => 'cancelled'])->assertStatus(200);

        $this->asAdmin();

        $this->patchJson("/api/admin/projects/{$project}/stage", ['stage' => 'client_liaison'])
            ->assertStatus(409)
            ->assertJson(['message' => 'Project yang dibatalkan tidak dapat mengubah tahap.']);
    }

    private function asAdmin(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);
    }

    private User $owner;

    private function project(): string
    {
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');

        $service = Service::query()->where('slug', 'basic')->firstOrFail();

        $payment = $this->postJson('/api/payments', [
            'serviceId' => $service->id,
            'scheduledAt' => '2026-10-15 10:00',
            'notes' => 'Kebutuhan analisis',
            'whatsapp' => '6281234567890',
        ])->assertStatus(201)->json('payment.id');

        $this->postJson("/api/payments/{$payment}/dummy-pay", ['method' => 'qris'])->assertStatus(200);

        return (string) $this->postJson('/api/consultations', [
            'serviceId' => $service->id,
            'title' => 'Analisis penjualan',
            'description' => 'Analisis data penjualan tahunan dengan regresi.',
            'scheduledAt' => '2026-10-20 09:00',
            'notes' => 'Dataset menyusul',
        ])->assertStatus(201)->json('consultation.id');
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('secret-password'),
            'name' => 'WRBN Admin',
            'role' => 'admin',
        ]);
    }
}
