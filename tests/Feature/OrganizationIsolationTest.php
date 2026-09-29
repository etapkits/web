<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_an_admin_does_not_see_another_organizations_boards(): void
    {
        $first = $this->registerBoard('machine-a', 'A tahtası');
        $other = Organization::factory()->create([
            'official_code' => '87654321',
            'enrollment_key' => 'other-enrollment-key',
        ]);
        $this->postJson('/api/device/register', [
            'enrollment_key' => 'other-enrollment-key',
            'machine_id' => 'machine-b',
            'hostname' => 'B tahtası',
        ])->assertCreated();

        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($admin)
            ->getJson('/api/panel/boards')
            ->assertOk()
            ->assertJsonCount(1, 'boards')
            ->assertJsonPath('boards.0.id', $first['board_id']);

        $this->actingAs($otherAdmin)
            ->getJson('/api/panel/boards')
            ->assertOk()
            ->assertJsonCount(1, 'boards')
            ->assertJsonMissing(['id' => $first['board_id']]);
    }

    public function test_a_board_cannot_move_to_another_organization_by_reregistering(): void
    {
        $this->registerBoard('machine-1', 'Fen-1');
        Organization::factory()->create([
            'official_code' => '87654321',
            'enrollment_key' => 'other-enrollment-key',
        ]);

        $this->postJson('/api/device/register', [
            'enrollment_key' => 'other-enrollment-key',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Bu tahta başka bir kuruma kayıtlı.');
    }

    public function test_teachers_are_scoped_to_their_organization(): void
    {
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        $teacher = Teacher::factory()->forOrganization($org)->create([
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
        ]);
        Teacher::factory()->forOrganization($other)->create([
            'first_name' => 'Ali',
            'last_name' => 'Demir',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/ogretmenler')
            ->assertOk();

        $this->getJson('/panel/ogretmenler/data?draw=1&start=0&length=25')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.first_name', 'Ayşe')
            ->assertJsonMissing(['first_name' => 'Ali']);

        $this->actingAs(User::factory()->create(['organization_id' => $other->id]))
            ->post('/panel/ogretmenler/'.$teacher->id.'/onay')
            ->assertNotFound();
    }
}
