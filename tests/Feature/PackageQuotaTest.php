<?php

namespace Tests\Feature;

use App\Models\MemberEmail;
use App\Models\Service;
use App\Models\User;
use App\Support\MemberPlan;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
        config(['wrbn.member_max_active' => 3]);
    }

    public function test_services_expose_the_quota_defined_on_the_pricing_page(): void
    {
        $services = collect($this->getJson('/api/services')->json('services'))->keyBy('slug');

        $this->assertSame(1, (int) $services['basic']['dataQuota']);
        $this->assertSame(1, (int) $services['basic']['revisionQuota']);
        $this->assertSame('Kesimpulan hasil', $services['basic']['deliverableLabel']);

        $this->assertSame(2, (int) $services['vip']['revisionQuota']);
        $this->assertSame('Rekomendasi & laporan lengkap', $services['vip']['deliverableLabel']);

        $this->assertSame(3, (int) $services['custom']['revisionQuota']);

    }

    public function test_a_member_is_redirected_to_pick_another_package_once_the_quota_is_used(): void
    {
        $this->actingAs($this->member('satu-data@example.com'), 'web');

        $this->postJson('/api/consultations', $this->payload('basic'))->assertStatus(201);

        $this->postJson('/api/consultations', $this->payload('basic'))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Kuota paket Basic sudah habis (1 data). Pilih dan bayar paket baru untuk mengirim data berikutnya.',
            ]);
    }

    public function test_cancelling_a_project_frees_the_package_quota(): void
    {
        $user = $this->member('batal@example.com');
        $this->actingAs($user, 'web');

        $consultationId = $this->postJson('/api/consultations', $this->payload('basic'))
            ->assertStatus(201)
            ->json('consultation.id');

        $this->patchJson("/api/consultations/{$consultationId}", ['status' => 'cancelled'])
            ->assertStatus(200);

        $this->postJson('/api/consultations', $this->payload('basic'))->assertStatus(201);
    }

    public function test_a_general_account_that_pays_again_for_the_same_package_hits_the_quota(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $service = $this->service('basic');

        $this->pay($this->createPayment($service));
        $this->postJson('/api/consultations', $this->payload('basic'))->assertStatus(201);

        $second = $this->createPayment($service);
        $this->pay($second);
        DB::table('payments')->where('id', $second)->update(['paid_at' => now()->addMinute()->toDateTimeString()]);

        $this->postJson('/api/consultations', $this->payload('basic'))
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Kuota paket Basic sudah habis (1 data). Pilih dan bayar paket baru untuk mengirim data berikutnya.',
            ]);

        $this->pay($this->createPayment($this->service('vip')));

        $this->postJson('/api/consultations', $this->payload('vip'))->assertStatus(201);
    }

    public function test_the_dashboard_reports_how_much_of_each_package_is_left(): void
    {
        $this->actingAs($this->member('kuota@example.com'), 'web');

        $this->postJson('/api/consultations', $this->payload('vip'))->assertStatus(201);

        $packages = collect($this->getJson('/api/client/dashboard')->json('packages'))->keyBy('slug');

        $this->assertSame(1, (int) $packages['vip']['used']);
        $this->assertSame(0, (int) $packages['vip']['remaining']);
        $this->assertSame(0, (int) $packages['basic']['used']);
        $this->assertSame(1, (int) $packages['basic']['remaining']);
        $this->assertSame(2, (int) $packages['vip']['revisionQuota']);
    }

    private function service(string $slug): Service
    {
        return Service::query()->where('slug', $slug)->firstOrFail();
    }

    private function payload(string $slug): array
    {
        return [
            'serviceId' => $this->service($slug)->id,
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

    private function createPayment(Service $service): string
    {
        return (string) $this->postJson('/api/payments', [
            'serviceId' => $service->id,
            'scheduledAt' => '2026-10-15 10:00',
            'notes' => 'Kebutuhan analisis',
            'whatsapp' => '6281234567890',
        ])
            ->assertStatus(201)
            ->json('payment.id');
    }

    private function pay(string $paymentId): void
    {
        $this->postJson("/api/payments/{$paymentId}/dummy-pay", ['method' => 'qris'])
            ->assertStatus(200)
            ->assertJsonPath('payment.status', 'paid');
    }
}
