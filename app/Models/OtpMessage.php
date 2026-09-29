<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type',
    'sendto',
    'message',
    'status',
    'claimed_at',
    'sended_at',
])]
class OtpMessage extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LOGIN = 'login';

    public const TYPE_ATTENDANCE = 'yoklama';

    protected $table = 'otp';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'sended_at' => 'datetime',
        ];
    }

    public static function queue(string $phone, string $message, string $type = self::TYPE_LOGIN): self
    {
        return static::query()->create([
            'type' => $type,
            'sendto' => $phone,
            'message' => $message,
            'status' => 'pending',
        ]);
    }
}
