<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Organization;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\RegistersBoards;
use Tests\TestCase;

class AttendanceOverviewTest extends TestCase
{
    use RefreshDatabase, RegistersBoards;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_sees_each_class_and_who_took_each_lesson(): void
    {
        Carbon::setTestNow('2026-09-28 07:15:00');
        $org = $this->organization();
        $other = Organization::factory()->create(['official_code' => '87654321']);
        $teacher = Teacher::factory()->forOrganization($org)->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        $absent = Student::factory()->forOrganization($org)->create(['class_name' => '10-A']);
        Student::factory()->forOrganization($org)->create(['class_name' => '9-A']);
        Student::factory()->forOrganization($other)->create(['class_name' => '11-C']);

        $this->actingAs($teacher, 'teacher')
            ->postJson('/api/ogretmen/yoklama', ['class_name' => '10-A', 'lesson' => 2, 'marks' => [$absent->id => 'absent']])
            ->assertCreated();

        AttendanceSession::query()->create([
            'organization_id' => $org->id, 'class_name' => '9-A', 'date' => '2026-09-27', 'lesson' => 1,
            'teacher_id' => $teacher->id, 'teacher_name' => 'Dünkü Öğretmen', 'teacher_phone' => $teacher->phone, 'student_count' => 1,
        ]);

        $this->actingAs(User::factory()->create(), 'web');

        $this->get('/panel/yoklamalar')->assertOk()->assertSee('8. ders')->assertSee('Yoklamalar');

        $this->getJson('/panel/yoklamalar/data?draw=1&start=0&length=-1')
            ->assertOk()
            ->assertJsonPath('date', '2026-09-28')
            ->assertJsonPath('summary', ['classes' => 2, 'taken' => 1, 'expected' => 16])
            ->assertJsonPath('data.0.class_name', '9-A')
            ->assertJsonPath('data.0.lessons.1', null)
            ->assertJsonPath('data.1.class_name', '10-A')
            ->assertJsonPath('data.1.lessons.2.teacher', 'Ayşe Yılmaz')
            ->assertJsonPath('data.1.lessons.2.time', '10:15')
            ->assertJsonPath('data.1.lessons.2.absent', 1)
            ->assertJsonPath('data.1.lessons.1', null)
            ->assertJsonMissing(['class_name' => '11-C']);

        $this->getJson('/panel/yoklamalar/data?draw=1&start=0&length=-1&date=2026-09-27')
            ->assertJsonPath('date', '2026-09-27')
            ->assertJsonPath('data.0.lessons.1.teacher', 'Dünkü Öğretmen');

        $this->getJson('/panel/yoklamalar/data?draw=1&start=0&length=-1&date=2030-01-01')
            ->assertJsonPath('date', '2026-09-28');
    }

    public function test_attendance_list_is_filtered_by_url_parameters(): void
    {
        Carbon::setTestNow('2026-09-28 07:15:00');
        $org = $this->organization();
        $ayse = Teacher::factory()->forOrganization($org)->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        $ali = Teacher::factory()->forOrganization($org)->create(['first_name' => 'Ali', 'last_name' => 'Demir']);
        $s1 = Student::factory()->forOrganization($org)->create(['student_no' => '11', 'first_name' => 'ELİF', 'last_name' => 'KAYA', 'class_name' => '9-A']);
        $s2 = Student::factory()->forOrganization($org)->create(['student_no' => '12', 'class_name' => '9-A']);
        $s3 = Student::factory()->forOrganization($org)->create(['student_no' => '21', 'class_name' => '10-B']);

        $this->actingAs($ayse, 'teacher')->postJson('/api/ogretmen/yoklama', [
            'class_name' => '9-A', 'lesson' => 1, 'marks' => [$s1->id => 'absent', $s2->id => 'late'],
        ])->assertCreated();
        $this->actingAs($ali, 'teacher')->postJson('/api/ogretmen/yoklama', [
            'class_name' => '9-A', 'lesson' => 2, 'marks' => [$s1->id => 'absent'],
        ])->assertCreated();
        $this->postJson('/api/ogretmen/yoklama', [
            'class_name' => '10-B', 'lesson' => 1, 'marks' => [$s3->id => 'late'],
        ])->assertCreated();

        $this->actingAs(User::factory()->create(), 'web');

        $this->get('/panel/yoklamalar/liste?sinif=9-A&ders=2&ogretmen='.$ali->id)
            ->assertOk()
            ->assertSee('<small class="topbar-org">· '.e($org->name).'</small>', false)
            ->assertSee('<option value="9-A" selected>', false)
            ->assertSee('<option value="2" selected>', false)
            ->assertSee('<option value="'.$ali->id.'" selected>', false);

        $base = '/panel/yoklamalar/liste/data?draw=1&start=0&length=50';

        $this->getJson($base)->assertJsonPath('recordsTotal', 4)->assertJsonCount(3, 'sessions');

        $this->getJson($base.'&sinif=9-A&ders=1')
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.student_name', 'ELİF KAYA')
            ->assertJsonPath('data.0.status', 'absent')
            ->assertJsonPath('data.0.status_label', 'Gelmedi')
            ->assertJsonPath('data.0.teacher_name', 'Ayşe Yılmaz')
            ->assertJsonPath('data.0.time', '10:15')
            ->assertJsonPath('sessions.0.teacher', 'Ayşe Yılmaz');

        $this->getJson($base.'&ogretmen='.$ali->id)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonCount(2, 'sessions');

        $columns = http_build_query(['columns' => collect([
            ['class_name', 'class_name', true],
            ['lesson', 's.lesson', false],
            ['student_no', 'student_no', true],
            ['student_name', 'attendance_records.student_name', true],
            ['status', 'attendance_records.status', false],
            ['teacher_name', 's.teacher_name', true],
            ['time', 's.created_at', false],
        ])->map(fn ($c) => ['data' => $c[0], 'name' => $c[1], 'searchable' => $c[2] ? 'true' : 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']])->all()]);

        foreach (['kaya' => 2, '10-B' => 1, '21' => 1, 'Demir' => 2, 'yok' => 0] as $keyword => $count) {
            $this->getJson($base.'&'.$columns.'&search%5Bvalue%5D='.urlencode($keyword))
                ->assertOk()
                ->assertJsonMissingPath('error')
                ->assertJsonPath('recordsFiltered', $count);
        }

        $this->getJson($base.'&durum=late')->assertJsonPath('recordsTotal', 2);
        $this->getJson($base.'&tarih=2026-09-27')->assertJsonPath('recordsTotal', 0)->assertJsonCount(0, 'sessions');
    }

    public function test_teachers_cannot_open_the_overview(): void
    {
        $teacher = Teacher::factory()->forOrganization($this->organization())->create();

        $this->actingAs($teacher, 'teacher')
            ->getJson('/panel/yoklamalar/data')
            ->assertUnauthorized();
    }
}
