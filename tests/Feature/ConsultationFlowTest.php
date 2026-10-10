<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsultationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
    }

    public function test_project_requires_a_paid_payment_before_it_can_be_created(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $service = $this->service();

        $this->postJson('/api/consultations', $this->payload($service))
            ->assertStatus(403)
            ->assertJson(['message' => 'Paket belum dibayar. Selesaikan payment terlebih dahulu.']);

        $paymentId = $this->createPayment($service);

        $this->postJson('/api/consultations', $this->payload($service))
            ->assertStatus(403)
            ->assertJson(['message' => 'Paket belum dibayar. Selesaikan payment terlebih dahulu.']);

        $this->pay($paymentId);

        $this->postJson('/api/consultations', $this->payload($service))->assertStatus(201);
    }

    public function test_a_paid_payment_cannot_be_reused_for_a_second_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $service = $this->service();

        $this->pay($this->createPayment($service));

        $this->postJson('/api/consultations', $this->payload($service))->assertStatus(201);

        $this->postJson('/api/consultations', $this->payload($service))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Pembayaran ini sudah dipakai untuk project. Buat payment baru untuk project berikutnya.',
            ]);
    }

    public function test_dummy_payment_is_disabled_in_production(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $service = $this->service();
        $paymentId = $this->createPayment($service);

        config(['wrbn.allow_dummy_pay' => false]);
        $this->app['env'] = 'production';

        $this->postJson("/api/payments/{$paymentId}/dummy-pay", ['method' => 'qris'])
            ->assertStatus(403)
            ->assertJson(['message' => 'Dummy payment dinonaktifkan.']);
    }

    public function test_project_code_follows_the_legacy_format(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $service = $this->service();

        $this->pay($this->createPayment($service));

        $response = $this->postJson('/api/consultations', $this->payload($service));
        $response->assertStatus(201);

        $projectCode = $response->json('consultation.project_code');

        $this->assertMatchesRegularExpression('/^WRBN-\d{8}-[A-Z0-9]{6}$/', (string) $projectCode);
    }

    public function test_file_upload_only_accepts_whitelisted_types(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $service = $this->service();

        $this->pay($this->createPayment($service));
        $consultationId = $this->postJson('/api/consultations', $this->payload($service))
            ->assertStatus(201)
            ->json('consultation.id');

        $this->post("/api/consultations/{$consultationId}/files", [
            'file' => UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('file.mimeType', 'application/pdf')
            ->assertJsonPath('file.originalName', 'laporan.pdf');

        $this->post("/api/consultations/{$consultationId}/files", [
            'file' => UploadedFile::fake()->create('virus.exe', 1, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Tipe file tidak diizinkan.']);
    }

    private function service(string $slug = 'basic'): Service
    {
        return Service::query()->where('slug', $slug)->firstOrFail();
    }

    private function payload(Service $service): array
    {
        return [
            'serviceId' => $service->id,
            'title' => 'Analisis penjualan',
            'description' => 'Analisis data penjualan tahunan dengan regresi.',
            'scheduledAt' => '2026-10-20 09:00',
            'notes' => 'Dataset menyusul',
        ];
    }

    private function createPayment(Service $service): string
    {
        $response = $this->postJson('/api/payments', [
            'serviceId' => $service->id,
            'scheduledAt' => '2026-10-15 10:00',
            'notes' => 'Kebutuhan analisis',
            'whatsapp' => '6281234567890',
        ]);

        $response->assertStatus(201);

        return (string) $response->json('payment.id');
    }

    private function pay(string $paymentId): void
    {
        $this->postJson("/api/payments/{$paymentId}/dummy-pay", ['method' => 'qris'])
            ->assertStatus(200)
            ->assertJsonPath('payment.status', 'paid');
    }
}
