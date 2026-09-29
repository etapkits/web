<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\BuildsDataTables;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class TeacherController extends Controller
{
    use BuildsDataTables, InteractsWithOrganization;

    public function index(Request $request): View
    {
        $this->organizationId($request);

        return view('panel.teachers', [
            'user' => $request->user(),
            'organization' => $request->user()->organization,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $status = $request->string('status')->toString();

        $query = Teacher::query()
            ->where('organization_id', $orgId)
            ->when($status === 'approved', fn ($q) => $q->whereNotNull('approved_at'))
            ->when($status === 'pending', fn ($q) => $q->whereNull('approved_at'));

        if (! $this->hasRequestedOrder($request)) {
            $query->orderByRaw('approved_at is null desc')
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        return DataTables::eloquent($query)
            ->escapeColumns([])
            ->addColumn('phone_display', fn (Teacher $t) => PhoneNumber::display($t->phone))
            ->addColumn('approved', fn (Teacher $t) => $t->isApproved())
            ->addColumn('approve_url', fn (Teacher $t) => route('panel.teachers.approve', $t))
            ->addColumn('destroy_url', fn (Teacher $t) => route('panel.teachers.destroy', $t))
            ->filterColumn('phone', $this->phoneFilter('phone'))
            ->only(['id', 'first_name', 'last_name', 'phone', 'phone_display', 'approved', 'approve_url', 'destroy_url'])
            ->toJson();
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $this->organizationId($request);
        $data = $request->validate($this->rules(), $this->messages());
        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            return back()->withErrors(['phone' => 'Telefon numarası geçerli değil.'])->withInput();
        }

        if (Teacher::query()->where('phone', $phone)->exists()) {
            return back()->withErrors(['phone' => 'Bu telefon kayıtlı.'])->withInput();
        }

        Teacher::query()->create([
            'organization_id' => $orgId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $phone,
            'approved_at' => now(),
        ]);

        return redirect()->route('panel.teachers')->with('status', 'Öğretmen kaydedildi.');
    }

    public function approve(Request $request, Teacher $teacher): JsonResponse|RedirectResponse
    {
        $this->organizationTeacher($request, $teacher);

        if (! $teacher->isApproved()) {
            $teacher->approved_at = now();
            $teacher->save();
        }

        return $this->done($request, 'Öğretmen onaylandı.');
    }

    public function destroy(Request $request, Teacher $teacher): JsonResponse|RedirectResponse
    {
        $this->organizationTeacher($request, $teacher);
        $teacher->delete();

        return $this->done($request, 'Öğretmen silindi.');
    }

    private function done(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('panel.teachers')->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:40', 'regex:/^[\p{L}][\p{L} .\'-]*$/u'],
            'last_name' => ['required', 'string', 'max:40', 'regex:/^[\p{L}][\p{L} .\'-]*$/u'],
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'first_name.required' => 'Ad gerekli.',
            'first_name.regex' => 'Ad geçersiz.',
            'last_name.required' => 'Soyad gerekli.',
            'last_name.regex' => 'Soyad geçersiz.',
            'phone.required' => 'Telefon gerekli.',
        ];
    }

    private function organizationTeacher(Request $request, Teacher $teacher): void
    {
        if ((int) $teacher->organization_id !== $this->organizationId($request)) {
            abort(404);
        }
    }
}
