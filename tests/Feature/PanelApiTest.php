<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class PanelApiTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_login_page_and_session_gate_the_panel(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="teacher-login-form"', false)
            ->assertSee('data-otp="'.url('/api/ogretmen/otp').'"', false)
            ->assertSee('js/teacher-login.js', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('super.login').'"', false);
        $this->get('/idare/giris')->assertOk()->assertSee('Parola');
        $this->get('/idare')->assertStatus(301)->assertRedirect('/idare/giris');
        $this->assertSame(url('/idare/giris'), route('login'));
        $this->get('/panel')->assertRedirect(route('login'));
        $this->getJson('/api/panel/boards')->assertUnauthorized();

        $user = User::factory()->create([
            'email' => 'yonetici@okul.test',
            'password' => 'gizli-parola',
        ]);

        $this->postJson('/api/panel/login', [
            'email' => 'yonetici@okul.test',
            'password' => 'yanlis',
        ])->assertStatus(422);

        $this->postJson('/api/panel/login', [
            'email' => 'yonetici@okul.test',
            'password' => 'gizli-parola',
        ])->assertOk()->assertJsonPath('email', $user->email);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Kayıtlı tahtalar')
            ->assertSee('Kamera yalnızca şifreli (HTTPS) adreste çalışır.')
            ->assertDontSee('>Aç<', false);

        $this->postJson('/api/panel/logout')->assertOk();
        $this->get('/panel')->assertRedirect(route('login'));
    }

    public function test_panel_lists_name_state_and_last_seen_without_a_remote_unlock(): void
    {
        $registered = $this->registerBoard('lab-board', 'Laboratuvar');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/panel/boards')
            ->assertOk()
            ->assertJsonPath('boards.0.name', 'Laboratuvar')
            ->assertJsonPath('boards.0.state', 'pending')
            ->assertJsonPath('boards.0.state_label', 'Onay bekliyor')
            ->assertJsonPath('boards.0.can_unlock', false)
            ->assertJsonPath('boards.0.can_lock', false)
            ->assertJsonPath('boards.0.can_shutdown', false);

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$registered['board_id'].'/unlock')
            ->assertStatus(409);

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$registered['board_id'].'/lock')
            ->assertStatus(409);

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$registered['board_id'], [
                'name' => 'Fen laboratuvarı',
                'approval' => 'approved',
            ])->assertOk();

        $this->postJson('/api/device/heartbeat', [
            'state' => 'unlocked',
        ], $this->deviceHeaders($registered['device_token']))->assertOk();

        $this->actingAs($user)
            ->getJson('/api/panel/boards')
            ->assertJsonPath('boards.0.name', 'Fen laboratuvarı')
            ->assertJsonPath('boards.0.state', 'unlocked')
            ->assertJsonPath('boards.0.state_label', 'Açık')
            ->assertJsonPath('boards.0.can_unlock', true)
            ->assertJsonPath('boards.0.can_lock', true);

        $this->travel((int) config('etakit.offline_seconds') + 1)->seconds();

        $this->actingAs($user)
            ->getJson('/api/panel/boards')
            ->assertJsonPath('boards.0.state', 'offline')
            ->assertJsonPath('boards.0.state_label', 'Çevrimdışı')
            ->assertJsonPath('boards.0.can_unlock', false)
            ->assertJsonPath('boards.0.can_lock', true);
    }

    public function test_a_panel_name_already_used_by_another_board_is_refused(): void
    {
        $first = $this->registerBoard('machine-1', '10-A');
        $second = $this->registerBoard('machine-2', '9-B');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$second['board_id'], ['name' => '10-a'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Bu tahta adı kullanımda.');

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$first['board_id'], ['name' => '10-A'])
            ->assertOk()
            ->assertJsonPath('board.name', '10-A');
    }

    public function test_locked_board_cannot_be_locked_again_from_the_list(): void
    {
        $registered = $this->registerBoard();

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/device/heartbeat', [
            'state' => 'locked',
        ], $this->deviceHeaders($registered['device_token']));

        $this->postJson('/api/panel/boards/'.$registered['board_id'].'/lock')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Yalnız açık tahta kilitlenebilir.');
    }

    public function test_open_and_locked_boards_can_be_opened_from_the_list(): void
    {
        $locked = $this->registerBoard('machine-locked', 'Kilitli tahta');
        $open = $this->registerBoard('machine-open', 'Açık tahta');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$locked['board_id'], ['approval' => 'approved']);
        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$open['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/device/heartbeat', [
            'state' => 'locked',
        ], $this->deviceHeaders($locked['device_token']))->assertOk();
        $this->postJson('/api/device/heartbeat', [
            'state' => 'unlocked',
        ], $this->deviceHeaders($open['device_token']))->assertOk();

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$locked['board_id'].'/unlock')
            ->assertOk()
            ->assertJsonPath('message', 'Açma komutu gönderildi.');

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$open['board_id'].'/unlock')
            ->assertOk()
            ->assertJsonPath('message', 'Açma komutu gönderildi.');

        $this->getJson('/api/device/commands', $this->deviceHeaders($locked['device_token']))
            ->assertOk()
            ->assertJsonPath('command.type', 'unlock');

        $this->getJson('/api/device/commands', $this->deviceHeaders($open['device_token']))
            ->assertOk()
            ->assertJsonPath('command.type', 'unlock');
    }
}
