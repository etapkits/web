<?php

namespace Tests\Feature;

use App\Models\OtpMessage;
use App\Models\Teacher;
use App\Models\TeacherOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class EtaotpQueueTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    public function test_claim_rejects_a_missing_token(): void
    {
        $this->postJson('/api/etaotp/claim')->assertUnauthorized();
    }

    public function test_phone_step_is_claimed_and_marked_sent_by_the_extension(): void
    {
        $this->organization();
        $teacher = Teacher::factory()->forOrganization($this->organization())->create([
            'phone' => '905321112233',
        ]);

        $this->postJson('/api/ogretmen/otp', ['phone' => '0532 111 22 33'])->assertOk();

        $claimed = $this->withToken('test-token')
            ->postJson('/api/etaotp/claim')
            ->assertOk()
            ->assertJsonPath('sendto', '905321112233');

        $code = preg_replace('/\D+/', '', (string) $claimed->json('message'));
        $this->assertSame(
            hash('sha256', (string) $code),
            TeacherOtp::query()->where('teacher_id', $teacher->id)->value('code_hash'),
        );
        $this->assertDatabaseHas('otp', [
            'id' => $claimed->json('id'),
            'status' => 'sending',
        ]);

        $this->withToken('wrong')->postJson('/api/etaotp/'.$claimed->json('id').'/sent')->assertUnauthorized();

        $this->withToken('test-token')
            ->postJson('/api/etaotp/'.$claimed->json('id').'/sent')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('otp', [
            'id' => $claimed->json('id'),
            'status' => 'sent',
            'message' => '',
        ]);
        $this->assertNotNull(DB::table('otp')->where('id', $claimed->json('id'))->value('sended_at'));

        $this->withToken('test-token')->postJson('/api/etaotp/claim')->assertNoContent();
    }

    public function test_login_messages_are_always_claimed_before_attendance_messages(): void
    {
        $this->travelTo(now()->subMinutes(10));
        $oldAttendance = OtpMessage::queue('905321112201', 'yoklama 1', OtpMessage::TYPE_ATTENDANCE);
        $this->travelBack();
        $login = OtpMessage::queue('905321112202', 'giriş');
        $newAttendance = OtpMessage::queue('905321112203', 'yoklama 2', OtpMessage::TYPE_ATTENDANCE);

        $claim = fn () => $this->withToken('test-token')->postJson('/api/etaotp/claim');

        $claim()->assertOk()->assertJsonPath('id', $login->id)->assertJsonPath('type', 'login');
        $claim()->assertOk()->assertJsonPath('id', $oldAttendance->id)->assertJsonPath('type', 'yoklama');

        $late = OtpMessage::queue('905321112204', 'giriş 2');
        $claim()->assertOk()->assertJsonPath('id', $late->id);
        $claim()->assertOk()->assertJsonPath('id', $newAttendance->id);
        $claim()->assertNoContent();

        $this->assertSame('sending', $oldAttendance->fresh()->status);

        $this->travel(4)->minutes();
        $claim()->assertOk()->assertJsonPath('id', $login->id);
    }
}
