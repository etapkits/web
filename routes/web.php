<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel\AttendanceController as PanelAttendanceController;
use App\Http\Controllers\Panel\BoardController;
use App\Http\Controllers\Panel\LockSettingController;
use App\Http\Controllers\Panel\SessionController;
use App\Http\Controllers\Panel\StudentController;
use App\Http\Controllers\Panel\TeacherController;
use App\Http\Controllers\Panel\UnlockController;
use App\Http\Controllers\PanelPageController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\Super\OrganizationController;
use App\Http\Controllers\Super\PageController as SuperPageController;
use App\Http\Controllers\Super\SessionController as SuperSessionController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\PageController as TeacherPageController;
use App\Http\Controllers\Teacher\RegisterController as TeacherRegisterController;
use App\Http\Controllers\Teacher\SessionController as TeacherSessionController;
use App\Http\Controllers\TermsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/privacy', PrivacyController::class)->name('privacy');
Route::get('/terms', TermsController::class)->name('terms');
Route::view('/cikis', 'signed-out')->name('signed-out');

Route::get('/idare/giris', [PanelPageController::class, 'login'])->name('login')->middleware('guest:web');
Route::permanentRedirect('/idare', '/idare/giris');
Route::middleware('auth:web')->group(function () {
    Route::get('/panel', [PanelPageController::class, 'index'])->name('panel');
    Route::get('/panel/ayarlar', [LockSettingController::class, 'edit'])->name('panel.settings');
    Route::post('/panel/ayarlar', [LockSettingController::class, 'update'])->name('panel.settings.update');
    Route::get('/panel/ogretmenler', [TeacherController::class, 'index'])->name('panel.teachers');
    Route::get('/panel/ogretmenler/data', [TeacherController::class, 'data'])->name('panel.teachers.data');
    Route::post('/panel/ogretmenler', [TeacherController::class, 'store'])->name('panel.teachers.store');
    Route::post('/panel/ogretmenler/{teacher}/onay', [TeacherController::class, 'approve'])->name('panel.teachers.approve');
    Route::delete('/panel/ogretmenler/{teacher}', [TeacherController::class, 'destroy'])->name('panel.teachers.destroy');
    Route::get('/panel/yoklamalar', [PanelAttendanceController::class, 'index'])->name('panel.attendance');
    Route::get('/panel/yoklamalar/data', [PanelAttendanceController::class, 'data'])->name('panel.attendance.data');
    Route::get('/panel/yoklamalar/liste', [PanelAttendanceController::class, 'list'])->name('panel.attendance.list');
    Route::get('/panel/yoklamalar/liste/data', [PanelAttendanceController::class, 'listData'])->name('panel.attendance.list.data');
    Route::get('/panel/yoklama-al', [TeacherAttendanceController::class, 'page'])->name('panel.attendance.take');
    Route::get('/panel/yoklama-al/data', [TeacherAttendanceController::class, 'data'])->name('panel.attendance.take.data');
    Route::post('/panel/yoklama-al', [TeacherAttendanceController::class, 'store'])->name('panel.attendance.take.store')->middleware('throttle:30,1');
    Route::get('/panel/ogrenciler', [StudentController::class, 'index'])->name('panel.students');
    Route::get('/panel/ogrenciler/data', [StudentController::class, 'data'])->name('panel.students.data');
    Route::post('/panel/ogrenciler', [StudentController::class, 'store'])->name('panel.students.store');
    Route::post('/panel/ogrenciler/import', [StudentController::class, 'import'])->name('panel.students.import');
    Route::get('/panel/ogrenciler/export', [StudentController::class, 'export'])->name('panel.students.export');
    Route::put('/panel/ogrenciler/{student}', [StudentController::class, 'update'])->name('panel.students.update');
    Route::delete('/panel/ogrenciler/{student}', [StudentController::class, 'destroy'])->name('panel.students.destroy');
});

Route::prefix('api/panel')->group(function () {
    Route::post('login', [SessionController::class, 'store'])
        ->middleware(['guest:web', 'throttle:5,1']);

    Route::middleware('auth:web')->group(function () {
        Route::post('logout', [SessionController::class, 'destroy']);
        Route::get('me', [SessionController::class, 'show']);
        Route::get('boards', [BoardController::class, 'index']);
        Route::patch('boards/{board}', [BoardController::class, 'update']);
        Route::post('boards/{board}/unlock', [BoardController::class, 'unlock']);
        Route::post('boards/{board}/lock', [BoardController::class, 'lock']);
        Route::post('boards/{board}/shutdown', [BoardController::class, 'shutdown']);
        Route::post('unlock', UnlockController::class)->middleware('throttle:12,1');
    });
});

Route::permanentRedirect('/ogretmen/giris', '/');
Route::get('/ogretmen/kayit', [TeacherRegisterController::class, 'create'])->name('teacher.register')->middleware('guest:teacher');
Route::post('/ogretmen/kayit', [TeacherRegisterController::class, 'store'])->name('teacher.register.store')->middleware(['guest:teacher', 'throttle:8,1']);
Route::get('/ogretmen', [TeacherPageController::class, 'index'])->name('teacher.panel')->middleware('auth:teacher');
Route::get('/ogretmen/yoklama', [TeacherAttendanceController::class, 'page'])->name('teacher.attendance')->middleware('auth:teacher');

Route::prefix('api/ogretmen')->group(function () {
    Route::post('otp', [TeacherSessionController::class, 'sendOtp'])
        ->middleware(['guest:teacher', 'throttle:teacher-otp']);
    Route::post('login', [TeacherSessionController::class, 'store'])
        ->middleware(['guest:teacher', 'throttle:5,1']);

    Route::middleware('auth:teacher')->group(function () {
        Route::post('logout', [TeacherSessionController::class, 'destroy']);
        Route::post('forget', [TeacherSessionController::class, 'forget']);
        Route::get('yoklama', [TeacherAttendanceController::class, 'data'])->name('teacher.attendance.data');
        Route::post('yoklama', [TeacherAttendanceController::class, 'store'])->name('teacher.attendance.store')->middleware('throttle:30,1');
        Route::get('boards', [BoardController::class, 'index']);
        Route::post('boards/{board}/unlock', [BoardController::class, 'unlock']);
        Route::post('boards/{board}/lock', [BoardController::class, 'lock']);
        Route::post('boards/{board}/shutdown', [BoardController::class, 'shutdown']);
        Route::post('unlock', UnlockController::class)->middleware('throttle:12,1');
    });
});

Route::get('/super/giris', [SuperPageController::class, 'login'])->name('super.login')->middleware('guest:super');
Route::middleware('auth:super')->prefix('super')->group(function () {
    Route::get('/', [OrganizationController::class, 'index'])->name('super.organizations');
    Route::get('/kurumlar/yeni', [OrganizationController::class, 'create'])->name('super.organizations.create');
    Route::post('/kurumlar', [OrganizationController::class, 'store'])->name('super.organizations.store');
    Route::get('/kurumlar/{organization}/duzenle', [OrganizationController::class, 'edit'])->name('super.organizations.edit');
    Route::put('/kurumlar/{organization}', [OrganizationController::class, 'update'])->name('super.organizations.update');
    Route::post('/kurumlar/{organization}/durum', [OrganizationController::class, 'toggle'])->name('super.organizations.toggle');
    Route::delete('/kurumlar/{organization}', [OrganizationController::class, 'destroy'])->name('super.organizations.destroy');
});

Route::prefix('api/super')->group(function () {
    Route::post('login', [SuperSessionController::class, 'store'])
        ->middleware(['guest:super', 'throttle:5,1']);

    Route::middleware('auth:super')->group(function () {
        Route::post('logout', [SuperSessionController::class, 'destroy']);
    });
});
