<?php

namespace App\Services;

use App\Enums\BoardApproval;
use App\Exceptions\BoardActionException;
use App\Models\Board;
use App\Models\Organization;
use RuntimeException;

class DeviceRegistrar
{
    /**
     * @return array{board: Board, token: string, created: bool}
     */
    public function register(string $machineId, ?string $hostname, Organization $organization, ?string $name = null): array
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $board = Board::query()->where('machine_id', $machineId)->first();
        $name = self::normalizeName($name);

        if ($board && (int) $board->organization_id !== (int) $organization->id) {
            throw new BoardActionException('Bu tahta başka bir kuruma kayıtlı.', 409);
        }

        if (! $board) {
            $board = new Board([
                'organization_id' => $organization->id,
                'machine_id' => $machineId,
                'hostname' => $hostname,
                'device_code' => $this->makeDeviceCode(),
                'token_hash' => $hash,
                'approval' => BoardApproval::Pending,
            ]);
            $created = true;
        } else {
            $board->token_hash = $hash;

            if ($hostname !== null) {
                $board->hostname = $hostname;
            }

            $created = false;
        }

        if ($name !== null) {
            $this->rename($board, $name, save: false);
        } elseif (! $board->exists) {
            $board->name = $hostname !== null && $hostname !== '' ? $hostname : 'Tahta '.$board->device_code;
        }

        $board->save();

        return ['board' => $board, 'token' => $token, 'created' => $created];
    }

    public function rename(Board $board, string $name, bool $save = true): void
    {
        $name = self::normalizeName($name);

        if ($name === null || ! self::nameIsValid($name)) {
            throw new BoardActionException('Tahta adı geçersiz.');
        }

        $taken = Board::query()
            ->where('organization_id', $board->organization_id)
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($board->exists, fn ($query) => $query->whereKeyNot($board->id))
            ->exists();

        if ($taken) {
            throw new BoardActionException('Bu tahta adı kullanımda.', 409);
        }

        $board->name = $name;

        if ($save && $board->exists) {
            $board->save();
        }
    }

    public static function normalizeName(?string $name): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $name === '' ? null : $name;
    }

    public static function nameIsValid(string $name): bool
    {
        return (bool) preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/()]{0,79}$/u', $name);
    }

    private function makeDeviceCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $last = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';

            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, $last)];
            }

            if (! Board::query()->where('device_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Tahta kodu üretilemedi.');
    }
}
