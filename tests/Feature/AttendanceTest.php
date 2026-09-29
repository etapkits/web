<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LockSetting;
use App\Models\Organization;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_teacher_takes_attendance_once_per_class_and_lesson(): void
    {
        Carbon::setTestNow('2026-09-28 21:30:00');
        $org = $this->organization();
        $teacher = Teacher::factory()->forOrganization($org)->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        $other = Teacher::factory()->forOrganization($org)->create(['first_name' => 'Ali', 'last_name' => 'Demir']);
        $a = Student::factory()->forOrganization($org)->create(['student_no' => '10', 'class_name' => '9-A']);
        $b = Student::factory()->forOrganization($org)->create(['student_no' => '2', 'class_name' => '9-A']);
        $c = Student::factory()->forOrganization($org)->create(['student_no' => '3', 'class_name' => '9-A']);
        Student::factory()->forOrganization($org)->create(['class_name' => '9-B']);

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/yoklama', [
                'class_name' => '9-A',
                'lesson' => 3,
                'marks' => [$a->id => 'absent', $b->id => 'late'],
            ])->assertCreated()
            ->assertJsonPath('lesson.teacher', 'Ayşe Yılmaz')
            ->assertJsonPath('lesson.absent', 1)
            ->assertJsonPath('lesson.late', 1);

        $session = AttendanceSession::query()->sole();
        $this->assertSame($teacher->id, $session->teacher_id);
        $this->assertSame('Ayşe Yılmaz', $session->teacher_name);
        $this->assertSame('2026-09-29', $session->date->toDateString());
        $this->assertSame(3, $session->student_count);
        $this->assertSame(2, AttendanceRecord::query()->count());

        $this->actingAs($other, 'teacher')
            ->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 3, 'marks' => []])
            ->assertStatus(409)
            ->assertJsonPath('message', '9-A 3. ders yoklaması Ayşe Yılmaz tarafından 00:30 saatinde alınmış. Yeni yoklama alınamaz.');

        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 4])
            ->assertCreated();

        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '9-B', 'lesson' => 3])
            ->assertCreated();

        $response = $this->getJson('/api/ogretmen/yoklama?draw=1&class_name=9-A&start=0&length=-1')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('data.0.student_no', '2')
            ->assertJsonPath('data.2.student_no', '10')
            ->assertJsonPath('data.0.marks.3', 'late')
            ->assertJsonPath('data.2.marks.3', 'absent')
            ->assertJsonPath('lessons.2.taken', true)
            ->assertJsonPath('lessons.2.teacher', 'Ayşe Yılmaz')
            ->assertJsonPath('lessons.3.teacher', 'Ali Demir')
            ->assertJsonPath('lessons.0.taken', false);

        $this->assertCount(8, $response->json('lessons'));
        $this->assertEquals([], (array) $response->json('data.1.marks'));
    }

    public function test_admin_takes_attendance_from_the_panel_with_their_name(): void
    {
        Carbon::setTestNow('2026-09-29 08:00:00');
        $org = $this->organization();
        $admin = User::factory()->create(['organization_id' => $org->id, 'name' => 'Mehmet Kaya']);
        $teacher = Teacher::factory()->forOrganization($org)->create();
        $student = Student::factory()->forOrganization($org)->create(['class_name' => '10-B']);

        $this->actingAs($admin, 'web');

        $this->get('/panel')->assertOk()->assertSee('data-attendance-url="'.route('panel.attendance.take').'"', false);

        $this->get('/panel/yoklama-al?sinif=10-B')
            ->assertOk()
            ->assertSee('data-data-url="'.route('panel.attendance.take.data').'"', false)
            ->assertSee('data-store-url="'.route('panel.attendance.take.store').'"', false)
            ->assertSee('<option value="10-B" selected>', false)
            ->assertSee('href="'.route('panel.settings').'"', false);

        $this->postJson('/panel/yoklama-al', ['class_name' => '10-B', 'lesson' => 1, 'marks' => [$student->id => 'absent']])
            ->assertCreated()
            ->assertJsonPath('lesson.teacher', 'Mehmet Kaya (İdare)');

        $session = AttendanceSession::query()->sole();
        $this->assertNull($session->teacher_id);
        $this->assertSame($admin->id, $session->user_id);
        $this->assertSame('', $session->teacher_phone);

        $this->getJson('/panel/yoklama-al/data?draw=1&class_name=10-B&start=0&length=-1')
            ->assertOk()
            ->assertJsonPath('data.0.marks.1', 'absent')
            ->assertJsonPath('lessons.0.teacher', 'Mehmet Kaya (İdare)');

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/yoklama', ['class_name' => '10-B', 'lesson' => 1])
            ->assertStatus(409);
    }

    public function test_marked_students_are_queued_for_whatsapp_only_when_enabled(): void
    {
        Carbon::setTestNow('2026-09-29 08:00:00');
        $org = $this->organization();
        $teacher = Teacher::factory()->forOrganization($org)->create();
        $absent = Student::factory()->forOrganization($org)->create(['class_name' => '9-A', 'first_name' => 'Can', 'last_name' => 'Ak', 'parent_phone' => '0532 111 22 33']);
        $late = Student::factory()->forOrganization($org)->create(['class_name' => '9-A', 'first_name' => 'Ece', 'last_name' => 'Su', 'parent_phone' => '905321112244']);
        $noPhone = Student::factory()->forOrganization($org)->create(['class_name' => '9-A', 'parent_phone' => '']);
        Student::factory()->forOrganization($org)->create(['class_name' => '9-A', 'parent_phone' => '05329998877']);
        $marks = [$absent->id => 'absent', $late->id => 'late', $noPhone->id => 'absent'];

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 1, 'marks' => $marks])
            ->assertCreated();
        $this->assertDatabaseCount('otp', 0);

        LockSetting::query()->create([
            'organization_id' => $org->id,
            'idle_seconds' => 600,
            'lock_countdown_seconds' => 10,
            'offline_grace_seconds' => 45,
            'emergency_seconds' => 300,
            'attendance_sms_enabled' => true,
            'attendance_sms_template' => '{kurum}: {ogrenci} {sinif} {tarih} {ders}. ders {durum}',
        ]);

        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 2, 'marks' => $marks])
            ->assertCreated();

        $this->assertEqualsCanonicalizing([
            ['type' => 'yoklama', 'sendto' => '905321112233', 'message' => $org->name.': Can Ak 9-A 29.09.2026 2. ders Gelmedi', 'status' => 'pending'],
            ['type' => 'yoklama', 'sendto' => '905321112244', 'message' => $org->name.': Ece Su 9-A 29.09.2026 2. ders Geç geldi', 'status' => 'pending'],
        ], DB::table('otp')->get(['type', 'sendto', 'message', 'status'])->map(fn ($row) => (array) $row)->all());
    }

    public function test_marks_must_belong_to_the_selected_class_and_organization(): void
    {
        $org = $this->organization();
        $teacher = Teacher::factory()->forOrganization($org)->create();
        Student::factory()->forOrganization($org)->create(['class_name' => '9-A']);
        $wrongClass = Student::factory()->forOrganization($org)->create(['class_name' => '9-B']);
        $foreign = Student::factory()->forOrganization(Organization::factory()->create(['official_code' => '87654321']))->create(['class_name' => '9-A']);

        $this->actingAs($teacher, 'teacher');

        foreach ([$wrongClass, $foreign] as $student) {
            $this->postJson('/api/ogretmen/yoklama', [
                'class_name' => '9-A', 'lesson' => 1, 'marks' => [$student->id => 'absent'],
            ])->assertStatus(422);
        }

        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 9])->assertStatus(422);
        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '12-Z', 'lesson' => 1])->assertStatus(422);
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_attendance_page_preselects_class_from_board_name(): void
    {
        $org = $this->organization();
        $teacher = Teacher::factory()->forOrganization($org)->create();
        Student::factory()->forOrganization($org)->create(['class_name' => '9-A']);
        Student::factory()->forOrganization($org)->create(['class_name' => '10-B']);

        $this->actingAs($teacher, 'teacher')
            ->get('/ogretmen/yoklama?sinif='.urlencode('10B Sınıfı'))
            ->assertOk()
            ->assertSee('<option value="10-B" selected>', false)
            ->assertSee('8. ders');

        $this->get('/ogretmen')->assertOk()->assertSee('data-attendance-url="'.route('teacher.attendance').'"', false);
    }

    public function test_admin_cannot_use_teacher_attendance_endpoints(): void
    {
        $this->organization();

        $this->postJson('/api/ogretmen/yoklama', ['class_name' => '9-A', 'lesson' => 1])->assertUnauthorized();
        $this->get('/ogretmen/yoklama')->assertRedirect();
    }
}
