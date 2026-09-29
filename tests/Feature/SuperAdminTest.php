<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_an_organization_with_official_code(): void
    {
        $admin = SuperAdmin::factory()->create([
            'email' => 'super@etakit.test',
            'password' => 'gizli-parola',
        ]);

        $this->get('/super')->assertRedirect(route('super.login'));

        $this->postJson('/api/super/login', [
            'email' => 'super@etakit.test',
            'password' => 'gizli-parola',
        ])->assertOk()->assertJsonPath('email', $admin->email);

        $this->get('/super')
            ->assertOk()
            ->assertSee('Kurumlar');

        $this->post('/super/kurumlar', [
            'name' => 'Atatürk Ortaokulu',
            'official_code' => '12345678',
            'admin_name' => 'Okul Müdürü',
            'admin_email' => 'mudur@okul.test',
            'admin_password' => 'parola123',
        ])->assertRedirect(route('super.organizations'));

        $organization = Organization::query()->where('official_code', '12345678')->first();
        $this->assertNotNull($organization);
        $this->assertNotSame('', $organization->enrollment_key);
        $this->assertDatabaseHas('users', [
            'email' => 'mudur@okul.test',
            'organization_id' => $organization->id,
        ]);

        $this->postJson('/api/panel/login', [
            'email' => 'mudur@okul.test',
            'password' => 'parola123',
        ])->assertOk();
    }

    public function test_institution_admin_cannot_open_super_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/super')
            ->assertRedirect(route('super.login'));
    }
}
