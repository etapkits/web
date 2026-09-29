<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superEmail = (string) env('SUPER_ADMIN_EMAIL', '');
        $superPassword = (string) env('SUPER_ADMIN_PASSWORD', '');

        if ($superEmail !== '' && $superPassword !== '') {
            SuperAdmin::query()->updateOrCreate(
                ['email' => $superEmail],
                [
                    'name' => (string) env('SUPER_ADMIN_NAME', 'Süper yönetici'),
                    'password' => $superPassword,
                ],
            );
        }

        $password = (string) env('PANEL_ADMIN_PASSWORD', '');
        $email = (string) env('PANEL_ADMIN_EMAIL', 'admin@etakit.local');
        $code = (string) env('FIRST_ORG_CODE', '00000000');

        if ($password === '' || $email === '' || $code === '') {
            return;
        }

        $key = (string) config('etakit.enrollment_key');

        $organization = Organization::query()->where('official_code', $code)->first()
            ?? Organization::query()->where('enrollment_key', $key)->first();

        if (! $organization) {
            $organization = Organization::query()->create([
                'name' => (string) env('FIRST_ORG_NAME', 'İlk kurum'),
                'official_code' => $code,
                'enrollment_key' => $key !== '' ? $key : Organization::makeEnrollmentKey(),
            ]);
        } elseif ($key !== '' && $organization->enrollment_key !== $key) {
            $organization->enrollment_key = $key;
            $organization->save();
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organization->id,
                'name' => (string) env('PANEL_ADMIN_NAME', 'Yönetici'),
                'password' => $password,
            ],
        );
    }
}
