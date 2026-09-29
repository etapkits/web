<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelPageController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function index(Request $request): View
    {
        return view('panel.index', self::boardPage($request, true));
    }

    /**
     * @return array<string, mixed>
     */
    public static function boardPage(Request $request, bool $manage): array
    {
        $user = $request->user();

        return [
            'user' => $user,
            'organization' => $user->organization,
            'canManage' => $manage,
            'boardsUrl' => $manage ? url('/api/panel/boards') : url('/api/ogretmen/boards'),
            'unlockUrl' => $manage ? url('/api/panel/unlock') : url('/api/ogretmen/unlock'),
            'logoutUrl' => $manage ? url('/api/panel/logout') : url('/api/ogretmen/logout'),
            'loginUrl' => $manage ? route('login') : route('home'),
            'attendanceUrl' => $manage ? route('panel.attendance.take') : route('teacher.attendance'),
        ];
    }
}
