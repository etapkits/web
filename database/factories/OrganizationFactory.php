<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Okulu',
            'official_code' => fake()->unique()->numerify('########'),
            'enrollment_key' => Organization::makeEnrollmentKey(),
            'created_by' => null,
        ];
    }

    public function testing(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Test Okulu',
            'official_code' => '12345678',
            'enrollment_key' => 'test-enrollment-key',
        ]);
    }

    public function createdBy(SuperAdmin $admin): static
    {
        return $this->state(fn (): array => [
            'created_by' => $admin->id,
        ]);
    }
}
