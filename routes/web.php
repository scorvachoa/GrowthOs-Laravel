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
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\TaskHistoryController;
use App\Http\Controllers\AIController;

Route::get('/', function () {
    return redirect()->route('login');
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

    Route::get('/ai', [AIController::class, 'index'])->name('ai.index');
    Route::get('/ai/history', [AIController::class, 'history'])->name('ai.history');
    Route::get('/ai/history/{id}', [AIController::class, 'show'])->name('ai.show');
    Route::delete('/ai/history/{id}', [AIController::class, 'destroy'])->name('ai.destroy');
    Route::get('/ai/history/{id}/download', [AIController::class, 'downloadTxt'])->name('ai.download');
    Route::post('/ai/create-task', [AIController::class, 'createTask'])->name('ai.create-task');
    Route::post('/ai/generate', [AIController::class, 'generateScript'])->name('ai.generate');
    Route::post('/ai/audio', [AIController::class, 'generateAudio'])->name('ai.audio');
    Route::post('/ai/copy', [AIController::class, 'generateCopy'])->name('ai.copy');
    Route::post('/ai/phrases', [AIController::class, 'generatePhrases'])->name('ai.phrases');
    Route::post('/ai/copy-phrases', [AIController::class, 'generateCopyPhrases'])->name('ai.copy-phrases');

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

        Route::get('/task-history', [TaskHistoryController::class, 'index'])->name('task-history.index');
        Route::get('/task-history/{video_task}', [TaskHistoryController::class, 'show'])->name('task-history.show');

        Route::get('/ideas', [IdeaController::class, 'index'])->name('ideas.index');
        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::post('/ideas/import', [IdeaController::class, 'import'])->name('ideas.import');
        Route::get('/ideas/export', [IdeaController::class, 'export'])->name('ideas.export');
        Route::patch('/ideas/{idea}/used', [IdeaController::class, 'toggleUsed'])->name('ideas.toggle-used');
        Route::patch('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
    });
});

require __DIR__.'/auth.php';
