<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskReportController;
use App\Http\Controllers\ReportHistoryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\YoutubeController;
use App\Http\Controllers\VideoTaskController;
use App\Http\Controllers\ExtraTaskController;
use App\Http\Controllers\PlanningController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('users', UserController::class)->middleware('can:manage users');
    Route::resource('roles', RoleController::class)->middleware('can:manage roles');
    Route::get('/task-reports/pdf', [TaskReportController::class, 'exportPdf'])->middleware('can:view reports')->name('reports.pdf');
    Route::get('/report-history', [ReportHistoryController::class, 'index'])->middleware('can:view reports')->name('report-history.index');
    Route::get('/report-history/{report_history}/download', [ReportHistoryController::class, 'download'])->middleware('can:view reports')->name('report-history.download');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company');
    Route::post('/settings/channels', [SettingsController::class, 'storeChannel'])->name('settings.channels.store');
    Route::post('/settings/channels/{channel}', [SettingsController::class, 'updateChannel'])->name('settings.channels.update');
    Route::post('/settings/channels/{channel}/delete', [SettingsController::class, 'destroyChannel'])->name('settings.channels.destroy');

    Route::get('/youtube', [YoutubeController::class, 'index'])->middleware('can:view youtube')->name('youtube.index');

    Route::middleware('can:manage tasks')->group(function () {
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/calendar/snapshot', [PlanningController::class, 'snapshot'])->name('planning.snapshot');
        Route::get('/planning/tasks', [PlanningController::class, 'tasksByDate'])->name('planning.tasks-by-date');
        Route::get('/planning/occupied-blocks', [PlanningController::class, 'occupiedBlocks'])->name('planning.occupied-blocks');

        Route::redirect('/video-tasks', '/planning');

        Route::resource('video-tasks', VideoTaskController::class)->except(['index']);
        Route::patch('/video-tasks/{video_task}/status', [VideoTaskController::class, 'updateStatus'])->name('video-tasks.status');
        Route::post('/video-tasks/{video_task}/move', [VideoTaskController::class, 'move'])->name('video-tasks.move');

        Route::get('/extra-tasks', [ExtraTaskController::class, 'index'])->name('extra-tasks.index');
        Route::post('/extra-tasks', [ExtraTaskController::class, 'store'])->name('extra-tasks.store');
        Route::patch('/extra-tasks/{extra_task}', [ExtraTaskController::class, 'update'])->name('extra-tasks.update');
        Route::delete('/extra-tasks/{extra_task}', [ExtraTaskController::class, 'destroy'])->name('extra-tasks.destroy');
    });
});

require __DIR__.'/auth.php';
