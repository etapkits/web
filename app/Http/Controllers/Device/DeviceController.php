<?php

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Http\Request;

abstract class DeviceController extends Controller
{
    protected function board(Request $request): Board
    {
        $board = $request->attributes->get('board');

        if (! $board instanceof Board) {
            abort(401, 'Tahta kimliği gerekli.');
        }

        return $board;
    }
}
