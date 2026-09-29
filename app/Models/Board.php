<?php

namespace App\Models;

use App\Enums\BoardApproval;
use App\Enums\BoardState;
use App\Enums\DisplayState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'machine_id',
    'hostname',
    'device_code',
    'token_hash',
    'approval',
    'reported_state',
    'last_seen_at',
    'approved_at',
])]
#[Hidden(['token_hash'])]
class Board extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval' => BoardApproval::class,
            'reported_state' => BoardState::class,
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(BoardQrCode::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(BoardCommand::class);
    }

    public function markSeen(?BoardState $state = null): void
    {
        $this->last_seen_at = now();

        if ($state !== null) {
            $this->reported_state = $state;
        }

        $this->save();
    }

    public function isOnline(): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        return $this->last_seen_at->greaterThan(
            now()->subSeconds((int) config('etakit.offline_seconds'))
        );
    }

    public function displayState(): DisplayState
    {
        if ($this->approval === BoardApproval::Pending) {
            return DisplayState::Pending;
        }

        if ($this->approval === BoardApproval::Rejected) {
            return DisplayState::Rejected;
        }

        if (! $this->isOnline()) {
            return DisplayState::Offline;
        }

        return match ($this->reported_state) {
            BoardState::Unlocked => DisplayState::Unlocked,
            BoardState::ShuttingDown => DisplayState::ShuttingDown,
            default => DisplayState::Locked,
        };
    }

    public function canUnlock(): bool
    {
        $state = $this->displayState();

        return $state === DisplayState::Locked || $state === DisplayState::Unlocked;
    }

    public function canLock(): bool
    {
        return $this->approval === BoardApproval::Approved
            && $this->reported_state === BoardState::Unlocked;
    }

    public function canShutdown(): bool
    {
        if ($this->approval !== BoardApproval::Approved) {
            return false;
        }

        return ! ($this->reported_state === BoardState::ShuttingDown && $this->isOnline());
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function toPanelArray(bool $manage = true): array
    {
        $state = $this->displayState();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'device_code' => $this->device_code,
            'approval' => $this->approval->value,
            'state' => $state->value,
            'state_label' => $state->label(),
            'reported_state' => $this->reported_state?->value,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'can_unlock' => $this->canUnlock(),
            'can_lock' => $this->canLock(),
            'can_shutdown' => $this->canShutdown(),
            'can_manage' => $manage,
        ];
    }
}
