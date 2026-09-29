<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class LockSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'idle_seconds',
        'lock_countdown_seconds',
        'offline_grace_seconds',
        'emergency_seconds',
        'session_seconds',
        'emergency_pin_hash',
        'attendance_sms_enabled',
        'attendance_sms_template',
    ];

    public const DEFAULT_ATTENDANCE_SMS_TEMPLATE = '{kurum}: {ogrenci} ({sinif}) {tarih} tarihli {ders}. ders yoklamasında "{durum}" olarak işaretlendi.';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_sms_enabled' => 'boolean',
        ];
    }

    public function sendsAttendanceSms(): bool
    {
        return (bool) $this->attendance_sms_enabled && trim((string) $this->attendance_sms_template) !== '';
    }

    public static function forOrganization(?int $organizationId): ?self
    {
        if ($organizationId === null || $organizationId < 1) {
            return null;
        }

        try {
            return static::query()->where('organization_id', $organizationId)->first();
        } catch (QueryException $exception) {
            if (static::tableIsMissing($exception)) {
                return null;
            }

            $message = $exception->getMessage();

            if (str_contains($message, 'organization_id')) {
                return static::query()->first();
            }

            if (str_contains($message, 'session_seconds')) {
                return static::query()->select([
                    'id',
                    'idle_seconds',
                    'lock_countdown_seconds',
                    'offline_grace_seconds',
                    'emergency_seconds',
                    'emergency_pin_hash',
                    'created_at',
                    'updated_at',
                ])->where('organization_id', $organizationId)->first();
            }

            throw $exception;
        }
    }

    public static function current(): ?self
    {
        try {
            $id = (int) (auth()->user()?->organization_id ?? 0);

            if ($id > 0) {
                return static::forOrganization($id);
            }

            return static::query()->first();
        } catch (QueryException $exception) {
            if (static::tableIsMissing($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    public static function tableIsMissing(QueryException $exception): bool
    {
        $info = $exception->errorInfo;
        $sqlState = is_array($info) ? (string) ($info[0] ?? '') : '';
        $message = $exception->getMessage();

        return $sqlState === '42S02'
            || str_contains($message, 'Base table or view not found')
            || str_contains($message, 'no such table');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDeviceArray(): array
    {
        return [
            'configured' => true,
            'idle_seconds' => $this->idle_seconds,
            'lock_countdown_seconds' => $this->lock_countdown_seconds,
            'offline_grace_seconds' => $this->offline_grace_seconds,
            'emergency_seconds' => $this->emergency_seconds,
            'session_seconds' => (int) ($this->session_seconds ?? 0),
            'emergency_pin_hash' => $this->emergency_pin_hash ?? '',
        ];
    }
}
