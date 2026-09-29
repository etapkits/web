<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'organization_id',
    'first_name',
    'last_name',
    'phone',
    'approved_at',
])]
#[Hidden(['remember_token'])]
class Teacher extends Authenticatable
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(TeacherOtp::class);
    }

    /**
     * Öğretmenin parolası yok; Laravel parola değeri boş olan hatırlama çerezini reddeder.
     * Telefon değişince eski hatırlama çerezleri de geçersiz olur.
     */
    public function getAuthPassword(): string
    {
        return (string) $this->phone;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getNameAttribute(): string
    {
        return $this->name();
    }
}
