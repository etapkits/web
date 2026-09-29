<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Board;
use App\Models\Teacher;
use Illuminate\Http\Request;

trait InteractsWithOrganization
{
    protected function organizationId(Request $request): int
    {
        $id = (int) ($request->user()?->organization_id ?? 0);

        if ($id < 1) {
            abort(403, 'Kurum bulunamadı.');
        }

        return $id;
    }

    protected function organizationBoard(Request $request, Board $board): Board
    {
        if ((int) $board->organization_id !== $this->organizationId($request)) {
            abort(404);
        }

        return $board;
    }

    protected function canManageBoards(Request $request): bool
    {
        return ! $request->user() instanceof Teacher;
    }
}
