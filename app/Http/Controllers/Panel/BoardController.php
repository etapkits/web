<?php

namespace App\Http\Controllers\Panel;

use App\Enums\BoardApproval;
use App\Enums\CommandType;
use App\Exceptions\BoardActionException;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Services\CommandQueue;
use App\Services\DeviceRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    use InteractsWithOrganization;

    public function index(Request $request): JsonResponse
    {
        $manage = $this->canManageBoards($request);
        $query = Board::query()->where('organization_id', $this->organizationId($request));

        if (! $manage) {
            $query->where('approval', BoardApproval::Approved);
        }

        $boards = $query
            ->orderByRaw("case approval when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->orderBy('name')
            ->get()
            ->map(fn (Board $board) => $board->toPanelArray($manage))
            ->values();

        return response()->json([
            'boards' => $boards,
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Board $board, DeviceRegistrar $registrar): JsonResponse
    {
        $this->assertCanManage($request);
        $this->organizationBoard($request, $board);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/()]*$/u'],
            'approval' => ['sometimes', Rule::in([
                BoardApproval::Approved->value,
                BoardApproval::Rejected->value,
            ])],
        ], [
            'name.string' => 'Tahta adı boş olamaz.',
            'name.max' => 'Tahta adı en fazla 80 karakter olabilir.',
            'name.regex' => 'Tahta adı geçersiz.',
            'approval.in' => 'Onay durumu geçersiz.',
        ]);

        if ($data === []) {
            throw new BoardActionException('Değiştirilecek bir alan yok.');
        }

        if (array_key_exists('name', $data)) {
            $registrar->rename($board, $data['name']);
        }

        if (array_key_exists('approval', $data)) {
            $approval = BoardApproval::from($data['approval']);
            $board->approval = $approval;

            if ($approval === BoardApproval::Approved) {
                $board->approved_at = now();
            }
        }

        $board->save();

        return response()->json(['board' => $board->toPanelArray(true)]);
    }

    public function unlock(Request $request, Board $board, CommandQueue $commands): JsonResponse
    {
        $this->organizationBoard($request, $board);
        $this->ensureApproved($board);

        if (! $board->canUnlock()) {
            throw new BoardActionException('Yalnız açık veya kilitli tahta açılabilir.', 409);
        }

        $command = $commands->enqueue($board, CommandType::Unlock);

        return response()->json([
            'message' => 'Açma komutu gönderildi.',
            'command_id' => $command->id,
            'board' => $board->toPanelArray($this->canManageBoards($request)),
        ]);
    }

    public function lock(Request $request, Board $board, CommandQueue $commands): JsonResponse
    {
        $this->organizationBoard($request, $board);
        $this->ensureApproved($board);

        if (! $board->canLock()) {
            throw new BoardActionException('Yalnız açık tahta kilitlenebilir.', 409);
        }

        $command = $commands->enqueue($board, CommandType::Lock);

        return response()->json([
            'message' => $board->isOnline()
                ? 'Kilitleme komutu gönderildi.'
                : 'Tahta çevrimdışı. Komut bağlantı dönünce gidecek.',
            'command_id' => $command->id,
            'board' => $board->toPanelArray($this->canManageBoards($request)),
        ]);
    }

    public function shutdown(Request $request, Board $board, CommandQueue $commands): JsonResponse
    {
        $this->organizationBoard($request, $board);
        $this->ensureApproved($board);

        if (! $board->canShutdown()) {
            throw new BoardActionException('Tahta zaten kapanıyor.', 409);
        }

        $command = $commands->enqueue($board, CommandType::Shutdown);

        return response()->json([
            'message' => $board->isOnline()
                ? 'Kapatma komutu gönderildi.'
                : 'Tahta çevrimdışı. Komut bağlantı dönünce gidecek.',
            'command_id' => $command->id,
            'board' => $board->toPanelArray($this->canManageBoards($request)),
        ]);
    }

    private function ensureApproved(Board $board): void
    {
        if ($board->approval !== BoardApproval::Approved) {
            throw new BoardActionException('Tahta onaylı değil.', 409);
        }
    }

    private function assertCanManage(Request $request): void
    {
        if (! $this->canManageBoards($request)) {
            abort(403, 'Bu işlem için yetkiniz yok.');
        }
    }
}
