<?php

namespace App\Services;

use App\Enums\BoardApproval;
use App\Enums\CommandType;
use App\Exceptions\BoardActionException;
use App\Models\Board;
use App\Models\BoardQrCode;
use Illuminate\Support\Facades\DB;

class QrCodeService
{
    public function __construct(private CommandQueue $commands) {}

    public function report(Board $board, string $code): BoardQrCode
    {
        return DB::transaction(function () use ($board, $code) {
            $expiresAt = now()->addSeconds((int) config('etakit.qr_ttl_seconds'));

            BoardQrCode::query()
                ->where('board_id', $board->id)
                ->whereNull('consumed_at')
                ->where('code', '!=', $code)
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $current = BoardQrCode::query()
                ->where('board_id', $board->id)
                ->where('code', $code)
                ->whereNull('consumed_at')
                ->first();

            if ($current) {
                $current->expires_at = $expiresAt;
                $current->save();

                return $current;
            }

            return BoardQrCode::query()->create([
                'board_id' => $board->id,
                'code' => $code,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function redeem(string $code, int $organizationId): Board
    {
        return DB::transaction(function () use ($code, $organizationId) {
            $qr = BoardQrCode::query()
                ->where('code', $code)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $qr) {
                throw new BoardActionException('Kod geçersiz veya süresi dolmuş.');
            }

            $board = Board::query()->whereKey($qr->board_id)->lockForUpdate()->first();

            if (! $board || $board->approval !== BoardApproval::Approved) {
                throw new BoardActionException('Bu tahta komut alamıyor.', 409);
            }

            if ((int) $board->organization_id !== $organizationId) {
                throw new BoardActionException('Kod geçersiz veya süresi dolmuş.');
            }

            $qr->consumed_at = now();
            $qr->save();

            $this->commands->enqueue($board, CommandType::Unlock);

            return $board;
        });
    }
}
