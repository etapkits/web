<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    /**
     * @param  array{name: string, official_code: string, admin_name: string, admin_email: string, admin_password: string}  $data
     */
    public function create(SuperAdmin $admin, array $data): Organization
    {
        return DB::transaction(function () use ($admin, $data) {
            $organization = Organization::query()->create([
                'name' => $data['name'],
                'official_code' => $data['official_code'],
                'enrollment_key' => Organization::makeEnrollmentKey(),
                'created_by' => $admin->id,
            ]);

            User::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]);

            return $organization;
        });
    }
}
