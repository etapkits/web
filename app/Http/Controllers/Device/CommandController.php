<?php

namespace App\Http\Controllers\Device;

use App\Enums\CommandStatus;
use App\Exceptions\BoardActionException;
use App\Models\BoardCommand;
use App\Services\CommandQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends DeviceController
{
    public function index(Request $request, CommandQueue $commands): JsonResponse
    {
        $configured = (int) config('etakit.command_wait_seconds');
        $wait = max(0, min((int) $request->query('wait', $configured), $configured));

        if ($wait > 0) {
            set_time_limit($wait + 10);
        }

        $board = $this->board($request);
        $board->markSeen();
        $deadline = microtime(true) + $wait;

        do {
            $command = $commands->claim($board->fresh() ?? $board);

            if ($command) {
                return $this->commandResponse($command);
            }

            if ($wait === 0 || connection_aborted()) {
                break;
            }

            usleep(500_000);
        } while (microtime(true) < $deadline);

        return $this->commandResponse(null);
    }

    public function ack(Request $request, string $command): JsonResponse
    {
        $board = $this->board($request);
        $row = BoardCommand::query()
            ->where('board_id', $board->id)
            ->whereKey($command)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Komut bulunamadı.'], 404);
        }

        if ($row->status === CommandStatus::Expired) {
            throw new BoardActionException('Komutun süresi doldu.', 409);
        }

        if ($row->status !== CommandStatus::Acknowledged) {
            $row->status = CommandStatus::Acknowledged;
            $row->acknowledged_at = now();
            $row->save();
        }

        return response()->json(['ok' => true]);
    }

    private function commandResponse(?BoardCommand $command): JsonResponse
    {
        $board = $command?->board ?? $this->board(request())->fresh();

        return response()->json([
            'command' => $command ? [
                'id' => $command->id,
                'type' => $command->type->value,
            ] : null,
            'name' => $board?->name,
            'device_code' => $board?->device_code,
            'approval' => $board?->approval->value,
        ])->header('Cache-Control', 'no-store');
    }
}
