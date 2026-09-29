<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class SuperOrganizationTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::factory()->create();
    }

    public function test_the_list_offers_edit_toggle_and_delete(): void
    {
        $organization = $this->organization();

        $this->actingAs($this->superAdmin(), 'super')
            ->get('/super')
            ->assertOk()
            ->assertSee('Aktif')
            ->assertSee('href="'.route('super.organizations.edit', $organization).'"', false)
            ->assertSee('action="'.route('super.organizations.toggle', $organization).'"', false)
            ->assertSee('action="'.route('super.organizations.destroy', $organization).'"', false);
    }

    public function test_an_organization_can_be_edited(): void
    {
        $organization = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        $this->actingAs($this->superAdmin(), 'super');

        $this->get('/super/kurumlar/'.$organization->id.'/duzenle')->assertOk()->assertSee($organization->name);

        $this->put('/super/kurumlar/'.$organization->id, [
            'name' => 'Yeni Ad',
            'official_code' => $other->official_code,
            'is_active' => '1',
        ])->assertSessionHasErrors('official_code');

        $this->put('/super/kurumlar/'.$organization->id, [
            'name' => 'Yeni Ad',
            'official_code' => '11112222',
            'is_active' => '0',
        ])->assertRedirect(route('super.organizations'));

        $organization->refresh();
        $this->assertSame('Yeni Ad', $organization->name);
        $this->assertSame('11112222', $organization->official_code);
        $this->assertFalse($organization->is_active);
    }

    public function test_an_inactive_organization_blocks_logins_registrations_and_open_sessions(): void
    {
        $organization = $this->organization();
        $admin = User::factory()->create(['organization_id' => $organization->id, 'password' => 'Parola-1234']);
        $teacher = Teacher::factory()->forOrganization($organization)->create();

        $this->actingAs($this->superAdmin(), 'super')
            ->post('/super/kurumlar/'.$organization->id.'/durum')
            ->assertRedirect();
        $this->assertFalse($organization->refresh()->is_active);

        $this->actingAs($admin, 'web')->getJson('/api/panel/boards')->assertUnauthorized();
        $this->actingAs($teacher, 'teacher')->get('/ogretmen')->assertRedirect(route('home'));

        $this->postJson('/api/panel/login', ['email' => $admin->email, 'password' => 'Parola-1234'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Kurum pasif. Etakit yönetimiyle görüşün.');

        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Kurum pasif. Okul idaresiyle görüşün.');

        $this->post('/ogretmen/kayit', [
            'official_code' => $organization->official_code,
            'first_name' => 'Ali',
            'last_name' => 'Veli',
            'phone' => '05321234567',
        ])->assertSessionHasErrors('official_code');

        $this->postJson('/api/device/register', [
            'enrollment_key' => $organization->enrollment_key,
            'machine_id' => 'machine-x',
        ])->assertForbidden();

        $this->actingAs($this->superAdmin(), 'super')
            ->post('/super/kurumlar/'.$organization->id.'/durum');
        $this->assertTrue($organization->refresh()->is_active);
    }

    public function test_deleting_requires_the_official_code_and_removes_everything(): void
    {
        $registered = $this->registerBoard('machine-del', 'Silinecek');
        $organization = $this->organization();
        User::factory()->create(['organization_id' => $organization->id]);
        Teacher::factory()->forOrganization($organization)->create();
        $this->actingAs($this->superAdmin(), 'super');

        $this->from('/super')
            ->delete('/super/kurumlar/'.$organization->id, ['confirm_code' => '000'])
            ->assertRedirect('/super')
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($organization);

        $this->delete('/super/kurumlar/'.$organization->id, ['confirm_code' => $organization->official_code])
            ->assertRedirect(route('super.organizations'));

        $this->assertModelMissing($organization);
        $this->assertSame(0, Board::query()->whereKey($registered['board_id'])->count());
        $this->assertSame(0, User::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Teacher::query()->where('organization_id', $organization->id)->count());
    }

    public function test_only_super_admins_can_manage_organizations(): void
    {
        $organization = $this->organization();

        $this->actingAs(User::factory()->create(['organization_id' => $organization->id]), 'web')
            ->post('/super/kurumlar/'.$organization->id.'/durum')
            ->assertRedirect(route('super.login'));

        $this->assertTrue($organization->refresh()->is_active);
    }
}
