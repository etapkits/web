<?php

namespace Tests\Feature;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Board;
use App\Models\BoardCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_register_rejects_a_bad_enrollment_key(): void
    {
        $this->postJson('/api/device/register', [
            'enrollment_key' => 'yanlis',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('boards', 0);
    }

    public function test_reregister_rotates_the_token_and_keeps_approval(): void
    {
        $first = $this->registerBoard();
        $board = Board::query()->findOrFail($first['board_id']);

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/panel/boards/'.$board->id, ['approval' => 'approved'])
            ->assertOk();

        $second = $this->postJson('/api/device/register', [
            'enrollment_key' => 'test-enrollment-key',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
        ])->assertOk()->json();

        $this->assertSame($first['board_id'], $second['board_id']);
        $this->assertSame($first['device_code'], $second['device_code']);
        $this->assertSame('approved', $second['approval']);
        $this->assertNotSame($first['device_token'], $second['device_token']);

        $this->postJson('/api/device/heartbeat', ['state' => 'locked'], $this->deviceHeaders($first['device_token']))
            ->assertUnauthorized();

        $this->postJson('/api/device/heartbeat', ['state' => 'locked'], $this->deviceHeaders($second['device_token']))
            ->assertOk()
            ->assertJsonPath('device_code', $first['device_code']);
    }

    public function test_pending_board_receives_no_commands(): void
    {
        $registered = $this->registerBoard();

        BoardCommand::query()->create([
            'board_id' => $registered['board_id'],
            'type' => CommandType::Lock,
            'status' => CommandStatus::Pending,
        ]);

        $this->getJson('/api/device/commands', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJson(['command' => null]);

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved'])
            ->assertOk();

        $this->getJson('/api/device/commands', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJsonPath('command.type', 'lock');
    }

    public function test_unlock_requires_the_code_currently_on_the_board(): void
    {
        $registered = $this->registerBoard();
        $headers = $this->deviceHeaders($registered['device_token']);
        $current = $this->qrPayload($registered['device_code']);
        $previous = $this->qrPayload($registered['device_code']);

        $this->postJson('/api/device/qr', ['code' => $previous], $headers)->assertOk();
        $this->postJson('/api/device/qr', ['code' => $current], $headers)->assertOk();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/panel/unlock', ['code' => $current])
            ->assertStatus(409);

        $this->actingAs($user)
            ->postJson('/api/panel/unlock', ['code' => $previous])
            ->assertStatus(422);

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/panel/unlock', ['code' => $previous])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/panel/unlock', ['code' => $current])
            ->assertOk()
            ->assertJsonPath('message', 'Açma izni yazıldı.');

        $this->actingAs($user)
            ->postJson('/api/panel/unlock', ['code' => $current])
            ->assertStatus(422);

        $this->getJson('/api/device/commands', $headers)
            ->assertOk()
            ->assertJsonPath('command.type', 'unlock');
    }

    public function test_expired_qr_and_stale_unlock_are_rejected(): void
    {
        $registered = $this->registerBoard();
        $headers = $this->deviceHeaders($registered['device_token']);
        $code = $this->qrPayload($registered['device_code']);

        $this->postJson('/api/device/qr', ['code' => $code], $headers)->assertOk();

        $this->travel((int) config('etakit.qr_ttl_seconds') + 1)->seconds();

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/panel/unlock', ['code' => $code])
            ->assertStatus(422);

        $fresh = $this->qrPayload($registered['device_code']);
        $this->postJson('/api/device/qr', ['code' => $fresh], $headers)->assertOk();
        $this->postJson('/api/panel/unlock', ['code' => $fresh])->assertOk();

        $this->travel((int) config('etakit.unlock_ttl_seconds') + 1)->seconds();

        $this->getJson('/api/device/commands', $headers)
            ->assertOk()
            ->assertJson(['command' => null]);

        $this->assertDatabaseHas('board_commands', [
            'board_id' => $registered['board_id'],
            'type' => 'unlock',
            'status' => 'expired',
        ]);
    }

    public function test_offline_shutdown_is_delivered_when_the_board_returns(): void
    {
        $registered = $this->registerBoard();
        $headers = $this->deviceHeaders($registered['device_token']);

        $this->actingAs(User::factory()->create())
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/device/heartbeat', ['state' => 'unlocked'], $headers)->assertOk();
        $this->travel((int) config('etakit.offline_seconds') + 1)->seconds();

        $this->postJson('/api/panel/boards/'.$registered['board_id'].'/shutdown')
            ->assertOk()
            ->assertJsonPath('message', 'Tahta çevrimdışı. Komut bağlantı dönünce gidecek.');

        $delivered = $this->getJson('/api/device/commands', $headers)
            ->assertOk()
            ->json('command');

        $this->assertSame('shutdown', $delivered['type']);

        $this->postJson('/api/device/commands/'.$delivered['id'].'/ack', [], $headers)
            ->assertOk();

        $this->getJson('/api/device/commands', $headers)
            ->assertJson(['command' => null]);
    }

    public function test_an_unacknowledged_shutdown_is_not_delivered_again_after_reboot(): void
    {
        $registered = $this->registerBoard();
        $headers = $this->deviceHeaders($registered['device_token']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/device/heartbeat', ['state' => 'unlocked'], $headers)->assertOk();

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$registered['board_id'].'/shutdown')
            ->assertOk();

        $this->assertSame('shutdown', $this->getJson('/api/device/commands', $headers)->json('command.type'));

        $this->travel((int) config('etakit.shutdown_ttl_seconds') + 1)->seconds();

        $this->getJson('/api/device/commands', $headers)
            ->assertOk()
            ->assertJson(['command' => null]);

        $this->assertDatabaseHas('board_commands', [
            'board_id' => $registered['board_id'],
            'type' => 'shutdown',
            'status' => 'expired',
        ]);
    }

    public function test_delivered_lock_is_retried_until_acknowledged(): void
    {
        $registered = $this->registerBoard();
        $headers = $this->deviceHeaders($registered['device_token']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/panel/boards/'.$registered['board_id'], ['approval' => 'approved']);

        $this->postJson('/api/device/heartbeat', ['state' => 'unlocked'], $headers)->assertOk();

        $this->actingAs($user)
            ->postJson('/api/panel/boards/'.$registered['board_id'].'/lock')
            ->assertOk();

        $first = $this->getJson('/api/device/commands', $headers)->json('command.id');

        $this->travel((int) config('etakit.command_retry_seconds') + 1)->seconds();

        $second = $this->getJson('/api/device/commands', $headers)->json('command.id');

        $this->assertSame($first, $second);
    }

    public function test_board_code_stays_random_and_a_used_name_is_refused(): void
    {
        $this->organization();

        $this->postJson('/api/device/register', [
            'enrollment_key' => 'test-enrollment-key',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
            'name' => '10/A!',
        ])->assertUnprocessable();

        $first = $this->postJson('/api/device/register', [
            'enrollment_key' => 'test-enrollment-key',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
            'name' => '10-A',
            'device_code' => 'AB7K',
        ])->assertCreated()->json();

        $this->assertSame('10-A', $first['name']);
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}$/', $first['device_code']);

        $this->postJson('/api/device/register', [
            'enrollment_key' => 'test-enrollment-key',
            'machine_id' => 'machine-2',
            'hostname' => 'Fen-2',
            'name' => '10-a',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Bu tahta adı kullanımda.');

        $this->assertDatabaseCount('boards', 1);

        $kept = $this->postJson('/api/device/register', [
            'enrollment_key' => 'test-enrollment-key',
            'machine_id' => 'machine-1',
            'hostname' => 'Fen-1',
            'device_code' => 'CD8M',
        ])->assertOk()->json();

        $this->assertSame($first['board_id'], $kept['board_id']);
        $this->assertSame($first['device_code'], $kept['device_code']);
        $this->assertSame('10-A', $kept['name']);

        $headers = $this->deviceHeaders($kept['device_token']);

        $this->postJson('/api/device/name', ['name' => '9/B'], $headers)
            ->assertOk()
            ->assertJsonPath('name', '9/B')
            ->assertJsonPath('device_code', $first['device_code']);

        $this->postJson('/api/device/heartbeat', ['state' => 'locked'], $headers)
            ->assertOk()
            ->assertJsonPath('name', '9/B')
            ->assertJsonPath('device_code', $first['device_code']);

        $this->getJson('/api/device/commands?wait=0', $headers)
            ->assertOk()
            ->assertJsonPath('name', '9/B')
            ->assertJsonPath('device_code', $first['device_code']);
    }
}
