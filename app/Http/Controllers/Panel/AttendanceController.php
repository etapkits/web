<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    use InteractsWithOrganization;

    public function index(Request $request): View
    {
        $this->organizationId($request);

        return view('panel.attendance', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
            'date' => $this->date($request)->toDateString(),
            'today' => AttendanceSession::schoolNow()->toDateString(),
            'lessons' => AttendanceSession::lessonCount(),
            'listUrl' => route('panel.attendance.list'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $date = $this->date($request);

        $sessions = AttendanceSession::query()
            ->with('records')
            ->where('organization_id', $orgId)
            ->whereDate('date', $date->toDateString())
            ->get()
            ->groupBy('class_name');

        $classNames = Student::query()
            ->where('organization_id', $orgId)
            ->distinct()
            ->pluck('class_name')
            ->merge($sessions->keys())
            ->unique()
            ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
            ->values();

        $rows = $classNames->map(function (string $className) use ($sessions): array {
            $byLesson = ($sessions->get($className) ?? collect())->keyBy('lesson');
            $lessons = [];

            for ($lesson = 1; $lesson <= AttendanceSession::lessonCount(); $lesson++) {
                $lessons[$lesson] = $byLesson->has($lesson) ? $byLesson->get($lesson)->summary() : null;
            }

            return [
                'class_name' => $className,
                'taken' => count(array_filter($lessons)),
                'lessons' => $lessons,
            ];
        });

        $taken = $sessions->flatten()->count();

        return DataTables::collection($rows)
            ->escapeColumns([])
            ->with([
                'date' => $date->toDateString(),
                'date_label' => $date->locale('tr')->translatedFormat('j F Y, l'),
                'summary' => [
                    'classes' => $classNames->count(),
                    'taken' => $taken,
                    'expected' => $classNames->count() * AttendanceSession::lessonCount(),
                ],
            ])
            ->toJson();
    }

    public function list(Request $request): View
    {
        $orgId = $this->organizationId($request);
        $date = $this->date($request, 'tarih');
        $filters = $this->listFilters($request);
        $sessions = $this->sessionsOn($orgId, $date);

        $classNames = $sessions->pluck('class_name')->unique()->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values()->all();
        $teachers = $sessions->filter(fn ($s) => $s->teacher_id !== null)
            ->unique('teacher_id')
            ->mapWithKeys(fn ($s) => [$s->teacher_id => $s->teacher_name])
            ->sort()
            ->all();

        if ($filters['class_name'] !== '' && ! in_array($filters['class_name'], $classNames, true)) {
            $classNames[] = $filters['class_name'];
        }

        return view('panel.attendance-list', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
            'date' => $date->toDateString(),
            'today' => AttendanceSession::schoolNow()->toDateString(),
            'lessons' => AttendanceSession::lessonCount(),
            'classNames' => $classNames,
            'teachers' => $teachers,
            'filters' => $filters,
        ]);
    }

    public function listData(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $date = $this->date($request, 'tarih');
        $filters = $this->listFilters($request);

        $sessions = $this->sessionsOn($orgId, $date)
            ->when($filters['class_name'] !== '', fn ($c) => $c->where('class_name', $filters['class_name']))
            ->when($filters['teacher_id'] !== null, fn ($c) => $c->where('teacher_id', $filters['teacher_id']))
            ->when($filters['lesson'] !== null, fn ($c) => $c->where('lesson', $filters['lesson']))
            ->sort(fn ($a, $b) => strnatcasecmp($a->class_name, $b->class_name) ?: $a->lesson <=> $b->lesson)
            ->values();

        $query = AttendanceRecord::query()
            ->join('attendance_sessions as s', 's.id', '=', 'attendance_records.attendance_session_id')
            ->where('s.organization_id', $orgId)
            ->whereDate('s.date', $date->toDateString())
            ->when($filters['class_name'] !== '', fn ($q) => $q->where('s.class_name', $filters['class_name']))
            ->when($filters['teacher_id'] !== null, fn ($q) => $q->where('s.teacher_id', $filters['teacher_id']))
            ->when($filters['lesson'] !== null, fn ($q) => $q->where('s.lesson', $filters['lesson']))
            ->when($filters['status'] !== null, fn ($q) => $q->where('attendance_records.status', $filters['status']))
            ->select([
                'attendance_records.id',
                'attendance_records.student_no',
                'attendance_records.student_name',
                'attendance_records.status',
                's.class_name',
                's.lesson',
                's.teacher_name',
                's.created_at as taken_at',
            ]);

        if (empty($request->input('order'))) {
            $query->orderByRaw('length(s.class_name)')
                ->orderBy('s.class_name')
                ->orderBy('s.lesson')
                ->orderByRaw('length(attendance_records.student_no)')
                ->orderBy('attendance_records.student_no');
        }

        $tz = (string) config('etakit.school_timezone');

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->editColumn('status', fn (AttendanceRecord $r) => $r->status->value)
            ->addColumn('status_label', fn (AttendanceRecord $r) => $r->status->label())
            ->addColumn('time', fn (AttendanceRecord $r) => Carbon::parse($r->getRawOriginal('taken_at'), 'UTC')->setTimezone($tz)->format('H:i'))
            ->orderColumn('class_name', 'length(s.class_name) $1, s.class_name $1')
            ->orderColumn('student_no', 'length(attendance_records.student_no) $1, attendance_records.student_no $1')
            ->filterColumn('class_name', fn ($q, string $keyword) => $q->where('s.class_name', 'like', '%'.$keyword.'%'))
            ->filterColumn('student_no', fn ($q, string $keyword) => $q->where('attendance_records.student_no', 'like', '%'.$keyword.'%'))
            ->only(['id', 'class_name', 'lesson', 'student_no', 'student_name', 'status', 'status_label', 'teacher_name', 'time'])
            ->with([
                'date' => $date->toDateString(),
                'date_label' => $date->locale('tr')->translatedFormat('j F Y, l'),
                'sessions' => $sessions->map(fn (AttendanceSession $s) => ['class_name' => $s->class_name] + $s->summary())->all(),
            ])
            ->toJson();
    }

    /**
     * @return array{class_name: string, teacher_id: int|null, lesson: int|null, status: string|null}
     */
    private function listFilters(Request $request): array
    {
        $lesson = (int) $request->query('ders', 0);
        $teacher = (int) $request->query('ogretmen', 0);
        $status = AttendanceStatus::tryFrom((string) $request->query('durum', ''));

        return [
            'class_name' => mb_substr(trim((string) $request->query('sinif', '')), 0, 20),
            'teacher_id' => $teacher > 0 ? $teacher : null,
            'lesson' => $lesson >= 1 && $lesson <= AttendanceSession::lessonCount() ? $lesson : null,
            'status' => $status?->value,
        ];
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function sessionsOn(int $orgId, Carbon $date): Collection
    {
        return AttendanceSession::query()
            ->with('records')
            ->where('organization_id', $orgId)
            ->whereDate('date', $date->toDateString())
            ->get();
    }

    private function date(Request $request, string $key = 'date'): Carbon
    {
        $value = (string) $request->query($key, '');
        $today = AttendanceSession::schoolNow()->startOfDay();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $today;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, (string) config('etakit.school_timezone'));
        } catch (\Throwable) {
            return $today;
        }

        return $date && $date->format('Y-m-d') === $value && $date->lessThanOrEqualTo($today) ? $date : $today;
    }
}
