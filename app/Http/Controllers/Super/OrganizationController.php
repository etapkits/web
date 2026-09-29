<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Organization::query()
            ->withCount(['boards', 'teachers', 'users'])
            ->orderBy('name');

        $code = trim((string) $request->query('q', ''));

        if ($code !== '') {
            $query->where(function ($builder) use ($code) {
                $builder->where('official_code', $code)
                    ->orWhere('name', 'like', '%'.$code.'%');
            });
        }

        return view('super.organizations', [
            'user' => $request->user('super'),
            'organizations' => $query->get(),
            'q' => $code,
        ]);
    }

    public function create(Request $request): View
    {
        return view('super.create', [
            'user' => $request->user('super'),
        ]);
    }

    public function store(Request $request, OrganizationService $organizations): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'official_code' => ['required', 'regex:/^\d{4,12}$/', Rule::unique('organizations', 'official_code')],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Kurum adı gerekli.',
            'official_code.required' => 'Kurum kodu gerekli.',
            'official_code.regex' => 'Kurum kodu 4-12 haneli olmalı.',
            'official_code.unique' => 'Bu kurum kodu kayıtlı.',
            'admin_name.required' => 'Yönetici adı gerekli.',
            'admin_email.required' => 'Yönetici e-posta gerekli.',
            'admin_email.unique' => 'Bu e-posta kayıtlı.',
            'admin_password.required' => 'Yönetici parolası gerekli.',
            'admin_password.min' => 'Parola en az 8 karakter olmalı.',
        ]);

        $organization = $organizations->create($request->user('super'), $data);

        return redirect()
            ->route('super.organizations')
            ->with('status', $organization->name.' eklendi. Tahta kayıt anahtarı listede.');
    }

    public function edit(Request $request, Organization $organization): View
    {
        return view('super.edit', [
            'user' => $request->user('super'),
            'organization' => $organization,
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'official_code' => ['required', 'regex:/^\d{4,12}$/', Rule::unique('organizations', 'official_code')->ignore($organization->id)],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Kurum adı gerekli.',
            'official_code.required' => 'Kurum kodu gerekli.',
            'official_code.regex' => 'Kurum kodu 4-12 haneli olmalı.',
            'official_code.unique' => 'Bu kurum kodu kayıtlı.',
        ]);

        $organization->update([
            'name' => $data['name'],
            'official_code' => $data['official_code'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('super.organizations')
            ->with('status', $organization->name.' güncellendi.');
    }

    public function toggle(Organization $organization): RedirectResponse
    {
        $organization->update(['is_active' => ! $organization->is_active]);

        return back()->with('status', $organization->name.($organization->is_active ? ' aktif edildi.' : ' pasif yapıldı.'));
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        if (trim((string) $request->input('confirm_code')) !== $organization->official_code) {
            return back()->withErrors(['delete' => 'Silmek için kurum kodunu doğru yazın.']);
        }

        $name = $organization->name;
        $organization->delete();

        return redirect()
            ->route('super.organizations')
            ->with('status', $name.' ve tüm verileri silindi.');
    }
}
