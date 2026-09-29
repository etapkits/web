<?php

namespace Tests\Concerns;

use App\Models\Organization;

trait RegistersBoards
{
    protected function organization(array $overrides = []): Organization
    {
        $key = $overrides['enrollment_key'] ?? 'test-enrollment-key';
        $found = Organization::query()->where('enrollment_key', $key)->first();

        if ($found) {
            return $found;
        }

        return Organization::factory()->create(array_merge([
            'name' => $key === 'test-enrollment-key' ? 'Test Okulu' : 'Diğer Okul',
            'official_code' => $key === 'test-enrollment-key' ? '12345678' : fake()->unique()->numerify('########'),
            'enrollment_key' => $key,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function registerBoard(string $machineId = 'machine-1', string $hostname = 'Fen-1', ?string $enrollmentKey = null): array
    {
        $key = $enrollmentKey ?? 'test-enrollment-key';
        $this->organization(['enrollment_key' => $key]);

        return $this->postJson('/api/device/register', [
            'enrollment_key' => $key,
            'machine_id' => $machineId,
            'hostname' => $hostname,
        ])->assertCreated()->json();
    }

    /**
     * @return array<string, string>
     */
    protected function deviceHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    protected function qrPayload(string $deviceCode): string
    {
        return 'etakit:1:'.$deviceCode.':'.bin2hex(random_bytes(16));
    }
}
