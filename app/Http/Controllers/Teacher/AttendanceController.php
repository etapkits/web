<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    use InteractsWithOrganization;

    public function page(Request $request): View
    {
        $orgId = $this->organizationId($request);
        $classNames = $this->classNames($orgId);
        $manage = $this->canManageBoards($request);

        return view('teacher.attendance', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
            'menu' => $manage ? 'panel' : 'teacher',
            'dataUrl' => $manage ? route('panel.attendance.take.data') : route('teacher.attendance.data'),
            'storeUrl' => $manage ? route('panel.attendance.take.store') : route('teacher.attendance.store'),
            'classNames' => $classNames,
            'selected' => $this->guessClass((string) $request->query('sinif', ''), $classNames),
            'lessons' => AttendanceSession::lessonCount(),
            'today' => AttendanceSession::schoolNow()->locale('tr')->translatedFormat('j F Y, l'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $className = $request->string('class_name')->trim()->toString();
        $sessions = $this->todaySessions($orgId, $className);

        $marks = [];

        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if ($record->student_id !== null) {
                    $marks[$record->student_id][$session->lesson] = $record->status->value;
                }
            }
        }

        $query = Student::query()
            ->where('organization_id', $orgId)
            ->where('class_name', $className)
            ->when($className === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByRaw('length(student_no)')
            ->orderBy('student_no');

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('name', fn (Student $s) => $s->name())
            ->addColumn('marks', fn (Student $s) => (object) ($marks[$s->id] ?? []))
            ->filterColumn('name', function ($q, string $keyword): void {
                $q->where(fn ($w) => $w->where('first_name', 'like', "%{$keyword}%")->orWhere('last_name', 'like', "%{$keyword}%"));
            })
            ->with('lessons', $this->lessonSummaries($sessions))
            ->only(['id', 'student_no', 'name', 'marks'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $recorder = $this->recorder($request);

        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:20'],
            'lesson' => ['required', 'integer', 'min:1', 'max:'.AttendanceSession::lessonCount()],
            'marks' => ['nullable', 'array'],
            'marks.*' => ['required', Rule::enum(AttendanceStatus::class)],
        ], [
            'class_name.required' => 'Sınıf seçin.',
            'lesson.*' => 'Ders saati geçersiz.',
            'marks.*' => 'Yoklama durumu geçersiz.',
        ]);

        $students = Student::query()
            ->where('organization_id', $orgId)
            ->where('class_name', $data['class_name'])
            ->get()
            ->keyBy('id');

        if ($students->isEmpty()) {
            return response()->json(['message' => 'Bu sınıfta öğrenci yok.'], 422);
        }

        $marks = collect($data['marks'] ?? []);

        if ($marks->keys()->contains(fn ($id) => ! $students->has((int) $id))) {
            return response()->json(['message' => 'Listede bu sınıfa ait olmayan öğrenci var. Sayfayı yenileyin.'], 422);
        }

        $date = AttendanceSession::schoolNow()->toDateString();
        $lesson = (int) $data['lesson'];

        if ($existing = $this->findSession($orgId, $data['class_name'], $date, $lesson)) {
            return $this->alreadyTaken($existing);
        }

        try {
            $session = DB::transaction(function () use ($orgId, $data, $date, $lesson, $recorder, $students, $marks) {
                $session = AttendanceSession::query()->create([
                    'organization_id' => $orgId,
                    'class_name' => $data['class_name'],
                    'date' => $date,
                    'lesson' => $lesson,
                    ...$recorder,
                    'student_count' => $students->count(),
                ]);

                foreach ($marks as $studentId => $status) {
                    $student = $students->get((int) $studentId);
                    $session->records()->create([
                        'student_id' => $student->id,
                        'student_no' => $student->student_no,
                        'student_name' => $student->name(),
                        'status' => $status,
                    ]);
                }

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->alreadyTaken($this->findSession($orgId, $data['class_name'], $date, $lesson));
        }

        $session->load('records');
        $summary = $session->summary();

        $notifier = app(AttendanceNotifier::class);
        $messages = $notifier->messages($session, $students);

        if ($messages !== []) {
            try {
                $notifier->queue($messages);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => sprintf('%s %d. ders yoklaması kaydedildi. Gelmedi: %d, geç: %d.', $session->class_name, $lesson, $summary['absent'], $summary['late']),
            'lesson' => $summary,
        ], 201);
    }

    /**
     * @return array{teacher_id: int|null, user_id: int|null, teacher_name: string, teacher_phone: string}
     */
    private function recorder(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof Teacher) {
            return [
                'teacher_id' => $user->id,
                'user_id' => null,
                'teacher_name' => $user->name(),
                'teacher_phone' => $user->phone,
            ];
        }

        return [
            'teacher_id' => null,
            'user_id' => $user->id,
            'teacher_name' => mb_substr($user->name, 0, 90).' (İdare)',
            'teacher_phone' => '',
        ];
    }

    private function alreadyTaken(?AttendanceSession $session): JsonResponse
    {
        $message = $session
            ? sprintf('%s %d. ders yoklaması %s tarafından %s saatinde alınmış. Yeni yoklama alınamaz.', $session->class_name, $session->lesson, $session->teacher_name, $session->summary()['time'])
            : 'Bu ders için yoklama alınmış.';

        return response()->json(['message' => $message], 409);
    }

    private function findSession(int $orgId, string $className, string $date, int $lesson): ?AttendanceSession
    {
        return AttendanceSession::query()
            ->with('records')
            ->where('organization_id', $orgId)
            ->where('class_name', $className)
            ->whereDate('date', $date)
            ->where('lesson', $lesson)
            ->first();
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function todaySessions(int $orgId, string $className): Collection
    {
        if ($className === '') {
            return new Collection;
        }

        return AttendanceSession::query()
            ->with('records')
            ->where('organization_id', $orgId)
            ->where('class_name', $className)
            ->whereDate('date', AttendanceSession::schoolNow()->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @return list<array<string, mixed>>
     */
    private function lessonSummaries(Collection $sessions): array
    {
        $byLesson = $sessions->keyBy('lesson');
        $out = [];

        for ($lesson = 1; $lesson <= AttendanceSession::lessonCount(); $lesson++) {
            $out[] = $byLesson->has($lesson)
                ? $byLesson->get($lesson)->summary()
                : ['lesson' => $lesson, 'taken' => false];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function classNames(int $orgId): array
    {
        $names = Student::query()->where('organization_id', $orgId)->distinct()->pluck('class_name')->all();
        usort($names, 'strnatcasecmp');

        return $names;
    }

    /**
     * Boards are usually named after their classroom ("9-A", "9A Sınıfı"), so preselect a matching class.
     *
     * @param  list<string>  $classNames
     */
    private function guessClass(string $boardName, array $classNames): ?string
    {
        if (trim($boardName) === '') {
            return null;
        }

        $normalized = Student::normalizeClassName($boardName);

        if (in_array($normalized, $classNames, true)) {
            return $normalized;
        }

        if (preg_match('/(\d{1,2})\s*[-\/. ]?\s*(\p{L})(?!\p{L})/u', $boardName, $m)) {
            $candidate = Student::normalizeClassName($m[1].'-'.$m[2]);

            if (in_array($candidate, $classNames, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
