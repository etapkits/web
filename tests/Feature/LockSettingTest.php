<?php

namespace Tests\Feature;

use App\Models\LockSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class LockSettingTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_the_board_keeps_local_settings_until_the_server_has_a_record(): void
    {
        $registered = $this->registerBoard();
        $user = User::factory()->create();

        $this->getJson('/api/device/settings', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJsonPath('configured', false);

        $this->assertDatabaseCount('lock_settings', 0);

        $this->actingAs($user)
            ->get('/panel/ayarlar')
            ->assertOk()
            ->assertSee('Henüz kayıt yok');

        $this->actingAs($user)
            ->post('/panel/ayarlar', [
                'idle_minutes' => 10,
                'lock_countdown_seconds' => 5,
                'offline_grace_seconds' => 60,
                'emergency_minutes' => 30,
                'session_minutes' => 40,
                'emergency_pin' => '2468',
            ])
            ->assertRedirect(route('panel.settings'));

        $saved = LockSetting::forOrganization((int) $user->organization_id);
        $this->assertNotNull($saved);
        $this->assertSame(600, $saved->idle_seconds);
        $this->assertSame(5, $saved->lock_countdown_seconds);
        $this->assertSame(60, $saved->offline_grace_seconds);
        $this->assertSame(1800, $saved->emergency_seconds);
        $this->assertSame(2400, $saved->session_seconds);
        $this->assertStringStartsWith('pbkdf2_sha256$', (string) $saved->emergency_pin_hash);
        $this->assertStringNotContainsString('2468', (string) $saved->emergency_pin_hash);

        $this->getJson('/api/device/settings', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('idle_seconds', 600)
            ->assertJsonPath('lock_countdown_seconds', 5)
            ->assertJsonPath('offline_grace_seconds', 60)
            ->assertJsonPath('emergency_seconds', 1800)
            ->assertJsonPath('session_seconds', 2400)
            ->assertJsonPath('emergency_pin_hash', $saved->emergency_pin_hash);
    }

    public function test_attendance_sms_settings_are_saved_and_require_a_template(): void
    {
        $user = User::factory()->create();
        $base = [
            'idle_minutes' => 10,
            'lock_countdown_seconds' => 5,
            'offline_grace_seconds' => 60,
            'emergency_minutes' => 5,
            'session_minutes' => 40,
        ];

        $this->actingAs($user)->get('/panel/ayarlar')
            ->assertOk()
            ->assertSee('Yoklama alınınca öğrenciye bildirim gönder');

        $this->post('/panel/ayarlar', $base + ['attendance_sms_enabled' => '1', 'attendance_sms_template' => ' '])
            ->assertSessionHasErrors('attendance_sms_template');

        $this->post('/panel/ayarlar', $base + ['attendance_sms_enabled' => '1', 'attendance_sms_template' => '{ogrenci} {durum}'])
            ->assertRedirect(route('panel.settings'));

        $saved = LockSetting::forOrganization((int) $user->organization_id);
        $this->assertTrue($saved->attendance_sms_enabled);
        $this->assertSame('{ogrenci} {durum}', $saved->attendance_sms_template);
        $this->assertArrayNotHasKey('attendance_sms_template', $saved->toDeviceArray());

        $this->post('/panel/ayarlar', $base + ['attendance_sms_enabled' => '0', 'attendance_sms_template' => '{ogrenci}'])
            ->assertRedirect(route('panel.settings'));
        $this->assertFalse($saved->fresh()->attendance_sms_enabled);
    }

    public function test_a_missing_lock_settings_table_uses_local_board_settings(): void
    {
        $registered = $this->registerBoard();
        Schema::drop('lock_settings');

        $this->getJson('/api/device/settings', $this->deviceHeaders($registered['device_token']))
            ->assertOk()
            ->assertJsonPath('configured', false);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/panel/ayarlar')->assertOk()->assertSee('Henüz kayıt yok');

        Schema::create('lock_settings', function ($table) {
            $table->id();
            $table->unsignedInteger('idle_seconds');
            $table->unsignedSmallInteger('lock_countdown_seconds');
            $table->unsignedSmallInteger('offline_grace_seconds');
            $table->unsignedInteger('emergency_seconds');
            $table->string('emergency_pin_hash')->nullable();
            $table->timestamps();
        });

        $this->assertTrue(LockSetting::tableIsMissing(new QueryException(
            'mysql',
            'select * from lock_settings',
            [],
            new \PDOException('SQLSTATE[42S02]: Base table or view not found'),
        )));
    }
}
