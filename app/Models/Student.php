<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'student_no',
    'first_name',
    'last_name',
    'class_name',
    'parent_phone',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public static function normalizeClassName(string $raw): string
    {
        $value = preg_replace('/\s*-\s*/u', '-', trim($raw)) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_strtoupper($value, 'UTF-8');
    }
}
