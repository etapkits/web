<?php

namespace App\Http\Controllers\Panel;

use App\Exports\StudentExporter;
use App\Http\Controllers\Concerns\BuildsDataTables;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Imports\StudentImporter;
use App\Imports\StudentImportException;
use App\Imports\StudentListParser;
use App\Models\Student;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class StudentController extends Controller
{
    use BuildsDataTables, InteractsWithOrganization;

    public function index(Request $request): View
    {
        $orgId = $this->organizationId($request);

        return view('panel.students', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
            'classNames' => $this->classNames($orgId),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $query = Student::query()
            ->where('organization_id', $orgId)
            ->when($request->filled('class_name'), fn ($q) => $q->where('class_name', $request->string('class_name')->toString()));

        if (! $this->hasRequestedOrder($request)) {
            $query->orderByRaw('length(class_name)')
                ->orderBy('class_name')
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('parent_phone_display', fn (Student $s) => $s->parent_phone ? PhoneNumber::display($s->parent_phone) : '')
            ->addColumn('update_url', fn (Student $s) => route('panel.students.update', $s))
            ->addColumn('destroy_url', fn (Student $s) => route('panel.students.destroy', $s))
            ->orderColumn('class_name', 'length(class_name) $1, class_name $1')
            ->orderColumn('student_no', 'length(student_no) $1, student_no $1')
            ->filterColumn('parent_phone', $this->phoneFilter('parent_phone'))
            ->only(['id', 'student_no', 'first_name', 'last_name', 'class_name', 'parent_phone', 'parent_phone_display', 'update_url', 'destroy_url'])
            ->toJson();
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $data = $this->validated($request, $orgId);

        Student::query()->create(['organization_id' => $orgId] + $data);

        return response()->json(['message' => 'Öğrenci kaydedildi.', 'class_names' => $this->classNames($orgId)], 201);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $orgId = $this->organizationStudent($request, $student);
        $student->update($this->validated($request, $orgId, $student));

        return response()->json(['message' => 'Öğrenci güncellendi.', 'class_names' => $this->classNames($orgId)]);
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        $orgId = $this->organizationStudent($request, $student);
        $student->delete();

        return response()->json(['message' => 'Öğrenci silindi.', 'class_names' => $this->classNames($orgId)]);
    }

    public function export(Request $request, StudentExporter $exporter): StreamedResponse
    {
        $orgId = $this->organizationId($request);
        $className = $request->string('class_name')->trim()->toString();
        $book = $exporter->build($orgId, $className);

        $name = 'ogrenciler'
            .($className !== '' ? '-'.Str::slug($className) : '')
            .'-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($book): void {
            (new Xlsx($book))->save('php://output');
            $book->disconnectWorksheets();
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function import(Request $request, StudentListParser $parser, StudentImporter $importer): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xls,xlsx,csv'],
            'remove_missing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Dosya seçin.',
            'file.max' => 'Dosya en fazla 10 MB olabilir.',
            'file.extensions' => 'Yalnızca .xls, .xlsx veya .csv yükleyin.',
        ]);

        try {
            $parsed = $parser->parse($request->file('file')->getRealPath());
        } catch (StudentImportException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $result = $importer->import($orgId, $parsed['rows'], $request->boolean('remove_missing'));

        return response()->json([
            'message' => sprintf(
                '%d öğrenci okundu: %d yeni, %d güncellendi, %d aynı%s.',
                count($parsed['rows']),
                $result['created'],
                $result['updated'],
                $result['unchanged'],
                $result['deleted'] > 0 ? sprintf(', %d silindi', $result['deleted']) : '',
            ),
            'result' => $result,
            'warnings' => array_slice($parsed['warnings'], 0, 100),
            'warning_count' => count($parsed['warnings']),
            'class_names' => $this->classNames($orgId),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function validated(Request $request, int $orgId, ?Student $student = null): array
    {
        $request->merge([
            'student_no' => trim((string) $request->input('student_no')),
            'class_name' => Student::normalizeClassName((string) $request->input('class_name')),
        ]);

        $data = $request->validate([
            'student_no' => [
                'required', 'string', 'max:20', 'regex:'.StudentListParser::NO_PATTERN,
                Rule::unique('students')->where('organization_id', $orgId)->ignore($student),
            ],
            'first_name' => ['required', 'string', 'max:100', 'regex:'.StudentListParser::NAME_PATTERN],
            'last_name' => ['required', 'string', 'max:100', 'regex:'.StudentListParser::NAME_PATTERN],
            'class_name' => ['required', 'string', 'max:20', 'regex:/^[\p{L}\d][\p{L}\d \/.-]*$/u'],
            'parent_phone' => ['nullable', 'string', 'max:20'],
        ], [
            'student_no.required' => 'Öğrenci no gerekli.',
            'student_no.regex' => 'Öğrenci no yalnızca harf, rakam ve tire içerebilir.',
            'student_no.unique' => 'Bu öğrenci no kayıtlı.',
            'first_name.required' => 'Ad gerekli.',
            'first_name.regex' => 'Ad geçersiz.',
            'last_name.required' => 'Soyad gerekli.',
            'last_name.regex' => 'Soyad geçersiz.',
            'class_name.required' => 'Sınıf gerekli.',
            'class_name.regex' => 'Sınıf geçersiz. Örnek: 9-A',
        ]);

        $phone = null;

        if (filled($data['parent_phone'] ?? null)) {
            $phone = PhoneNumber::normalize($data['parent_phone']);

            if ($phone === null) {
                throw ValidationException::withMessages(['parent_phone' => 'Veli telefonu geçerli değil.']);
            }
        }

        $data['parent_phone'] = $phone;

        return $data;
    }

    /**
     * @return list<string>
     */
    private function classNames(int $orgId): array
    {
        $names = Student::query()
            ->where('organization_id', $orgId)
            ->distinct()
            ->pluck('class_name')
            ->all();

        usort($names, 'strnatcasecmp');

        return $names;
    }

    private function organizationStudent(Request $request, Student $student): int
    {
        $orgId = $this->organizationId($request);

        if ((int) $student->organization_id !== $orgId) {
            abort(404);
        }

        return $orgId;
    }
}
