<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
    }

    public function test_a_fresh_account_has_no_biodata(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/client/profile')
            ->assertStatus(200)
            ->assertJsonPath('profile.age', null)
            ->assertJsonPath('profile.institution', null)
            ->assertJsonPath('profile.city', null);
    }

    public function test_the_intake_wizard_can_save_biodata(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->putJson('/api/client/profile', [
            'age' => 21,
            'occupation' => 'Mahasiswa',
            'institution' => 'Universitas Gadjah Mada',
            'level' => 'S4',
            'city' => 'Yogyakarta',
            'referral' => 'Instagram',
        ])
            ->assertStatus(200)
            ->assertJsonPath('profile.age', 21)
            ->assertJsonPath('profile.occupation', 'Mahasiswa')
            ->assertJsonPath('profile.institution', 'Universitas Gadjah Mada')
            ->assertJsonPath('profile.level', 'S4')
            ->assertJsonPath('profile.city', 'Yogyakarta')
            ->assertJsonPath('profile.referral', 'Instagram');

        $this->getJson('/api/client/profile')
            ->assertStatus(200)
            ->assertJsonPath('profile.age', 21);
    }

    public function test_saving_the_profile_twice_updates_the_same_row(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->putJson('/api/client/profile', ['age' => 21, 'city' => 'Bandung'])
            ->assertStatus(200);

        $this->putJson('/api/client/profile', ['age' => 22, 'city' => 'Jakarta'])
            ->assertStatus(200)
            ->assertJsonPath('profile.age', 22)
            ->assertJsonPath('profile.city', 'Jakarta');

        $this->assertDatabaseCount('user_profiles', 1);
    }

    public function test_age_outside_thirteen_to_one_hundred_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->putJson('/api/client/profile', ['age' => 12])
            ->assertStatus(400)
            ->assertJson(['message' => 'Umur harus antara 13 dan 100.']);

        $this->putJson('/api/client/profile', ['age' => 101])
            ->assertStatus(400);

        $this->putJson('/api/client/profile', ['age' => 'bukan-angka'])
            ->assertStatus(400);

        $this->assertDatabaseCount('user_profiles', 0);
    }

    public function test_the_wizard_does_not_require_every_biodata_field(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->putJson('/api/client/profile', ['age' => 30])
            ->assertStatus(200)
            ->assertJsonPath('profile.age', 30)
            ->assertJsonPath('profile.occupation', null);
    }
}
