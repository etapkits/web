<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class TeacherBoardTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_a_teacher_can_only_control_approved_boards(): void
    {
        $registered = $this->registerBoard();
        $user = User::factory()->create();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create();

        $this->actingAs($teacher, 'teacher')
            ->getJson('/api/ogretmen/boards')
            ->assertOk()
            ->assertJsonCount(0, 'boards');

        $this->actingAs($user, 'web')
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved'])
            ->assertOk();

        $this->postJson('/api/device/heartbeat', [
            'state' => 'locked',
        ], $this->deviceHeaders($registered['device_token']))->assertOk();

        $this->actingAs($teacher, 'teacher')
            ->getJson('/api/ogretmen/boards')
            ->assertOk()
            ->assertJsonCount(1, 'boards')
            ->assertJsonPath('boards.0.can_unlock', true)
            ->assertJsonPath('boards.0.can_lock', false)
            ->assertJsonPath('boards.0.can_shutdown', true)
            ->assertJsonPath('boards.0.can_manage', false);

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/boards/'.$registered['board_id'].'/unlock')
            ->assertOk();

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/boards/'.$registered['board_id'].'/shutdown')
            ->assertOk()
            ->assertJsonPath('board.can_manage', false);

        $other = Teacher::factory()->forOrganization(Organization::factory()->create(['official_code' => '87654321']))->create();

        $this->actingAs($other, 'teacher')
            ->postJson('/api/ogretmen/boards/'.$registered['board_id'].'/shutdown')
            ->assertNotFound();

        $this->actingAs($teacher, 'teacher')
            ->patchJson('/api/ogretmen/boards/'.$registered['board_id'], ['name' => 'Yeni ad'])
            ->assertNotFound();

        Auth::guard('web')->logout();

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/panel/boards/'.$registered['board_id'].'/shutdown')
            ->assertUnauthorized();

        $this->actingAs($teacher, 'teacher')
            ->get('/panel/ayarlar')
            ->assertRedirect(route('login'));
    }

    public function test_otp_does_not_unlock_a_board(): void
    {
        $registered = $this->registerBoard();
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $teacher = Teacher::factory()->forOrganization($this->organization())->create();
        $this->postJson('/api/ogretmen/otp', ['phone' => $teacher->phone])->assertOk();

        $this->getJson('/api/device/commands', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJson(['command' => null]);
    }
}
