<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ContactPerson;
use App\Models\MemberEmail;
use App\Models\Service;
use App\Models\User;
use App\Support\MemberPlan;
use Database\Seeders\ContactPersonSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContactPersonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'wrbn.cp_general_name' => 'Rizky - Tim WRBN',
            'wrbn.cp_general_whatsapp' => '6281200000000',
        ]);

        $this->seed(ServiceSeeder::class);
        $this->seed(ContactPersonSeeder::class);
    }

    public function test_the_general_contact_person_is_public(): void
    {
        $this->getJson('/api/contact-person')
            ->assertStatus(200)
            ->assertJsonPath('contactPerson.name', 'Rizky - Tim WRBN')
            ->assertJsonPath('contactPerson.whatsapp', '6281200000000')
            ->assertJsonPath('contactPerson.waLink', 'https://wa.me/6281200000000');
    }

    public function test_the_endpoint_answers_with_null_when_no_contact_person_exists(): void
    {
        ContactPerson::query()->delete();

        $this->getJson('/api/contact-person')
            ->assertStatus(200)
            ->assertJsonPath('contactPerson', null);
    }

    public function test_a_new_project_is_assigned_to_the_general_contact_person(): void
    {
        $consultationId = $this->createProject();

        $this->assertDatabaseHas('project_assignments', [
            'consultation_id' => $consultationId,
            'role' => 'client_liaison',
            'assignee_name' => 'Rizky - Tim WRBN',
            'assignee_contact' => '6281200000000',
        ]);

        $this->assertDatabaseHas('consultations', [
            'id' => $consultationId,
            'contact_person' => 'Rizky - Tim WRBN',
        ]);
    }

    public function test_the_client_sees_the_contact_person_on_the_project_detail(): void
    {
        $consultationId = $this->createProject();

        $this->getJson('/api/consultations/'.$consultationId)
            ->assertStatus(200)
            ->assertJsonPath('clientContact.assignee_name', 'Rizky - Tim WRBN')
            ->assertJsonPath('clientContact.assignee_contact', '6281200000000');
    }

    public function test_intake_fields_are_stored_with_the_project(): void
    {
        $this->actingAs($this->member('intake@example.com'), 'web');

        $response = $this->postJson('/api/consultations', $this->payload([
            'needType' => 'Skripsi / Tugas Kuliah',
            'deadline' => '2026-11-30 23:59',
        ]))
            ->assertStatus(201);

        $this->assertDatabaseHas('consultations', [
            'id' => $response->json('consultation.id'),
            'need_type' => 'Skripsi / Tugas Kuliah',
            'deadline' => '2026-11-30 23:59',
        ]);
    }

    public function test_an_invalid_deadline_is_rejected(): void
    {
        $this->actingAs($this->member('tanggal@example.com'), 'web');

        $this->postJson('/api/consultations', $this->payload(['deadline' => 'kapan saja']))
            ->assertStatus(400)
            ->assertJson(['message' => 'Deadline tidak valid.']);
    }

    public function test_the_admin_sees_the_client_biodata_on_the_project(): void
    {
        $user = $this->member('biodata@example.com');
        $this->actingAs($user, 'web');

        $this->putJson('/api/client/profile', [
            'age' => 21,
            'occupation' => 'Mahasiswa',
            'institution' => 'IPB University',
            'level' => 'S4',
            'city' => 'Bogor',
        ])->assertStatus(200);

        $consultationId = $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(201)
            ->json('consultation.id');

        $this->actingAs($this->admin(), 'admin');
        $this->withSession(['admin_login_at' => now()->getTimestamp()]);

        $this->getJson('/api/admin/projects/'.$consultationId)
            ->assertStatus(200)
            ->assertJsonPath('clientProfile.age', 21)
            ->assertJsonPath('clientProfile.occupation', 'Mahasiswa')
            ->assertJsonPath('clientProfile.institution', 'IPB University')
            ->assertJsonPath('clientProfile.level', 'S4')
            ->assertJsonPath('clientProfile.city', 'Bogor');
    }

    private function createProject(): string
    {
        $this->actingAs($this->member('project@example.com'), 'web');

        return (string) $this->postJson('/api/consultations', $this->payload())
            ->assertStatus(201)
            ->json('consultation.id');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        $service = Service::query()->where('slug', 'basic')->firstOrFail();

        return $extra + [
            'serviceId' => $service->id,
            'title' => 'Analisis penjualan',
            'description' => 'Analisis data penjualan tahunan dengan regresi.',
            'scheduledAt' => '2026-10-20 09:00',
            'notes' => 'Dataset menyusul',
        ];
    }

    private function member(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);

        MemberEmail::query()->create(['email' => $email]);

        return MemberPlan::sync($user);
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
