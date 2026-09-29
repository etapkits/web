<?php

namespace App\Services;

use App\Enums\BoardApproval;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Board;
use App\Models\BoardCommand;
use Illuminate\Support\Facades\DB;

class CommandQueue
{
    public function enqueue(Board $board, CommandType $type): BoardCommand
    {
        $existing = BoardCommand::query()
            ->where('board_id', $board->id)
            ->where('type', $type->value)
            ->whereIn('status', [CommandStatus::Pending->value, CommandStatus::Delivered->value])
            ->orderBy('created_at')
            ->first();

        if ($existing && ! $this->isStale($existing)) {
            if ($type === CommandType::Unlock && $existing->status === CommandStatus::Pending) {
                $existing->created_at = now();
                $existing->save();
            }

            return $existing;
        }

        if ($existing) {
            $existing->status = CommandStatus::Expired;
            $existing->save();
        }

        return BoardCommand::query()->create([
            'board_id' => $board->id,
            'type' => $type,
            'status' => CommandStatus::Pending,
        ]);
    }

    public function claim(Board $board): ?BoardCommand
    {
        if ($board->approval !== BoardApproval::Approved) {
            return null;
        }

        return DB::transaction(function () use ($board) {
            $locked = Board::query()->whereKey($board->id)->lockForUpdate()->first();

            if (! $locked || $locked->approval !== BoardApproval::Approved) {
                return null;
            }

            $commands = BoardCommand::query()
                ->where('board_id', $locked->id)
                ->where(function ($query) {
                    $query->where('status', CommandStatus::Pending->value)
                        ->orWhere(function ($query) {
                            $query->where('status', CommandStatus::Delivered->value)
                                ->where('delivered_at', '<=', now()->subSeconds((int) config('etakit.command_retry_seconds')));
                        });
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($commands as $command) {
                if ($this->isStale($command)) {
                    $command->status = CommandStatus::Expired;
                    $command->save();

                    continue;
                }

                $command->status = CommandStatus::Delivered;
                $command->delivered_at = now();
                $command->save();

                return $command;
            }

            return null;
        });
    }

    private function isStale(BoardCommand $command): bool
    {
        if ($command->type === CommandType::Unlock) {
            return $command->created_at->lte(
                now()->subSeconds((int) config('etakit.unlock_ttl_seconds'))
            );
        }

        if ($command->type === CommandType::Shutdown && $command->status === CommandStatus::Delivered) {
            return $command->created_at->lte(
                now()->subSeconds((int) config('etakit.shutdown_ttl_seconds', 600))
            );
        }

        return false;
    }
}
