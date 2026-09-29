<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'organization_id',
    'class_name',
    'date',
    'lesson',
    'teacher_id',
    'user_id',
    'teacher_name',
    'teacher_phone',
    'student_count',
])]
class AttendanceSession extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'lesson' => 'integer',
            'student_count' => 'integer',
        ];
    }

    public static function schoolNow(): Carbon
    {
        return now((string) config('etakit.school_timezone'));
    }

    public static function lessonCount(): int
    {
        return max(1, (int) config('etakit.attendance_lessons'));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return array{lesson: int, taken: bool, teacher: string, time: string, absent: int, late: int, total: int}
     */
    public function summary(): array
    {
        $statuses = $this->records->countBy(fn (AttendanceRecord $r) => $r->status->value);

        return [
            'lesson' => $this->lesson,
            'taken' => true,
            'teacher' => $this->teacher_name,
            'time' => $this->created_at->setTimezone((string) config('etakit.school_timezone'))->format('H:i'),
            'absent' => (int) ($statuses[AttendanceStatus::Absent->value] ?? 0),
            'late' => (int) ($statuses[AttendanceStatus::Late->value] ?? 0),
            'total' => $this->student_count,
        ];
    }
}
