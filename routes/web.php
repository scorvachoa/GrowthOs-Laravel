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

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\YoutubeController;
use App\Http\Controllers\VideoTaskController;
use App\Http\Controllers\ExtraTaskController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\TaskHistoryController;
use App\Http\Controllers\AI\AudioController;
use App\Http\Controllers\AI\CopyController;
use App\Http\Controllers\AI\PhrasesController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\BackupController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => true,
        'canRegister' => true,
    ]);
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Manual
    Route::get('/manual', fn () => Inertia::render('Manual'))->name('manual');

    // Users
    Route::get('/users', [UserController::class, 'index'])->middleware('can:view users')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('can:create users')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:create users')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('can:edit users')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:edit users')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:delete users')->name('users.destroy');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:view roles')->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->middleware('can:create roles')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:create roles')->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('can:edit roles')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:edit roles')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:delete roles')->name('roles.destroy');

    // Planning
    Route::middleware('can:view planning')->group(function () {
        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/calendar/snapshot', [PlanningController::class, 'snapshot'])->name('planning.snapshot');
        Route::get('/planning/tasks', [PlanningController::class, 'tasksByDate'])->name('planning.tasks-by-date');
        Route::get('/planning/occupied-blocks', [PlanningController::class, 'occupiedBlocks'])->name('planning.occupied-blocks');
        Route::redirect('/video-tasks', '/planning');
    });
    Route::get('/video-tasks/create', [VideoTaskController::class, 'create'])->middleware('can:create planning')->name('video-tasks.create');
    Route::post('/video-tasks', [VideoTaskController::class, 'store'])->middleware('can:create planning')->name('video-tasks.store');
    Route::get('/video-tasks/{video_task}', [VideoTaskController::class, 'show'])->middleware('can:view planning')->name('video-tasks.show');
    Route::get('/video-tasks/{video_task}/edit', [VideoTaskController::class, 'edit'])->middleware('can:edit planning')->name('video-tasks.edit');
    Route::put('/video-tasks/{video_task}', [VideoTaskController::class, 'update'])->middleware('can:edit planning')->name('video-tasks.update');
    Route::patch('/video-tasks/{video_task}/status', [VideoTaskController::class, 'updateStatus'])->middleware('can:edit planning')->name('video-tasks.status');
    Route::post('/video-tasks/{video_task}/move', [VideoTaskController::class, 'move'])->middleware('can:edit planning')->name('video-tasks.move');
    Route::delete('/video-tasks/{video_task}', [VideoTaskController::class, 'destroy'])->middleware('can:delete planning')->name('video-tasks.destroy');
    Route::get('/extra-tasks', [ExtraTaskController::class, 'index'])->middleware('can:view planning')->name('extra-tasks.index');
    Route::post('/extra-tasks', [ExtraTaskController::class, 'store'])->middleware('can:create planning')->name('extra-tasks.store');
    Route::patch('/extra-tasks/{extra_task}', [ExtraTaskController::class, 'update'])->middleware('can:edit planning')->name('extra-tasks.update');
    Route::delete('/extra-tasks/{extra_task}', [ExtraTaskController::class, 'destroy'])->middleware('can:delete planning')->name('extra-tasks.destroy');
    Route::get('/planning/observation', [PlanningController::class, 'getObservation'])->middleware('can:view planning')->name('planning.observation.get');
    Route::post('/planning/observation', [PlanningController::class, 'saveObservation'])->middleware('can:view planning')->name('planning.observation.save');

    // Task History
    Route::get('/task-history', [TaskHistoryController::class, 'index'])->middleware('can:view tasks')->name('task-history.index');
    Route::get('/task-history/{video_task}', [TaskHistoryController::class, 'show'])->middleware('can:view tasks')->name('task-history.show');

    // Ideas
    Route::get('/ideas', [IdeaController::class, 'index'])->middleware('can:view ideas')->name('ideas.index');
    Route::post('/ideas', [IdeaController::class, 'store'])->middleware('can:create ideas')->name('ideas.store');
    Route::post('/ideas/import', [IdeaController::class, 'import'])->middleware('can:import ideas')->name('ideas.import');
    Route::get('/ideas/export', [IdeaController::class, 'export'])->middleware('can:export ideas')->name('ideas.export');
    Route::post('/ideas/bulk-update', [IdeaController::class, 'bulkUpdate'])->middleware('can:edit ideas')->name('ideas.bulk-update');
    Route::patch('/ideas/{idea}/used', [IdeaController::class, 'toggleUsed'])->middleware('can:edit ideas')->name('ideas.toggle-used');
    Route::patch('/ideas/{idea}', [IdeaController::class, 'update'])->middleware('can:edit ideas')->name('ideas.update');
    Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->middleware('can:delete ideas')->name('ideas.destroy');

    // Reports
    Route::get('/task-reports/pdf', [TaskReportController::class, 'exportPdf'])->middleware('can:download reports')->name('reports.pdf');
    Route::get('/report-history', [ReportHistoryController::class, 'index'])->middleware('can:view reports')->name('report-history.index');
    Route::get('/report-history/{report_history}/download', [ReportHistoryController::class, 'download'])->middleware('can:download reports')->name('report-history.download');
    Route::delete('/report-history/{report_history}', [ReportHistoryController::class, 'destroy'])->middleware('can:delete reports')->name('report-history.destroy');

    // Company CRUD
    Route::get('/company', [CompanyController::class, 'index'])->middleware('can:view empresa')->name('company.index');
    Route::get('/company/create', [CompanyController::class, 'create'])->middleware('can:create empresa')->name('company.create');
    Route::post('/company', [CompanyController::class, 'store'])->middleware('can:create empresa')->name('company.store');
    Route::get('/company/{company}', [CompanyController::class, 'show'])->middleware('can:view empresa')->name('company.show');
    Route::get('/company/{company}/edit', [CompanyController::class, 'edit'])->middleware('can:edit empresa')->name('company.edit');
    Route::put('/company/{company}', [CompanyController::class, 'update'])->middleware('can:edit empresa')->name('company.update');
    Route::delete('/company/{company}', [CompanyController::class, 'destroy'])->middleware('can:delete empresa')->name('company.destroy');

    // Company switcher (Super Admin only)
    Route::post('/company/switch', [CompanyController::class, 'switchCompany'])->middleware('can:view empresa');

    // Company channels & invites (keep specific routes before wildcards)
    Route::post('/company/channels', [CompanyController::class, 'storeChannel'])->middleware('can:create empresa');
    Route::post('/company/channels/{channel}', [CompanyController::class, 'updateChannel'])->middleware('can:edit empresa');
    Route::post('/company/channels/{channel}/delete', [CompanyController::class, 'destroyChannel'])->middleware('can:delete empresa');
    Route::post('/company/generate-invite', [CompanyController::class, 'generateInviteCode'])->middleware('can:edit empresa');

    // User Settings (per-user preferences)
    Route::get('/settings', [UserSettingsController::class, 'index'])->middleware('can:view configuracion')->name('settings.index');
    Route::put('/settings', [UserSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/backup-schedule', [UserSettingsController::class, 'updateBackupSchedule'])->middleware('can:configure backup')->name('settings.backup-schedule');

    // YouTube
    Route::get('/youtube', [YoutubeController::class, 'index'])->middleware('can:view youtube')->name('youtube.index');

    // AI Generator
    Route::get('/ai', [AIController::class, 'index'])->middleware('can:generate ai')->name('ai.index');
    Route::post('/ai/generate', [AIController::class, 'generateScript'])->middleware(['can:generate ai', 'throttle:10,1'])->name('ai.generate');
    Route::post('/ai/audio', [AudioController::class, 'generate'])->middleware(['can:generate ai', 'throttle:10,1'])->name('ai.audio');
    Route::post('/ai/copy', [CopyController::class, 'generate'])->middleware(['can:generate ai', 'throttle:10,1'])->name('ai.copy');
    Route::post('/ai/phrases', [PhrasesController::class, 'generate'])->middleware(['can:generate ai', 'throttle:10,1'])->name('ai.phrases');
    Route::post('/ai/copy-phrases', [PhrasesController::class, 'generateWithCopy'])->middleware(['can:generate ai', 'throttle:10,1'])->name('ai.copy-phrases');
    Route::post('/ai/create-task', [AIController::class, 'createTask'])->middleware('can:generate ai')->name('ai.create-task');

    Route::get('/ai/history', [AIController::class, 'history'])->middleware('can:view ai history')->name('ai.history');
    Route::get('/ai/history/{id}', [AIController::class, 'show'])->middleware('can:view ai history')->name('ai.show');
    Route::delete('/ai/history/{id}', [AIController::class, 'destroy'])->middleware('can:view ai history')->name('ai.destroy');
    Route::get('/ai/history/{id}/download', [AIController::class, 'downloadTxt'])->middleware('can:download ai')->name('ai.download');

    // Vacations
    Route::get('/vacations', [VacationController::class, 'index'])->middleware('can:view vacations')->name('vacations.index');
    Route::post('/vacations', [VacationController::class, 'store'])->middleware('can:view vacations')->name('vacations.store');
    Route::patch('/vacations/{vacation}/approve', [VacationController::class, 'approve'])->middleware('can:approve vacations')->name('vacations.approve');
    Route::patch('/vacations/{vacation}/reject', [VacationController::class, 'reject'])->middleware('can:reject vacations')->name('vacations.reject');
    Route::patch('/vacations/{vacation}', [VacationController::class, 'update'])->middleware('can:edit vacations')->name('vacations.update');
    Route::delete('/vacations/{vacation}', [VacationController::class, 'destroy'])->middleware('can:delete vacations')->name('vacations.destroy');

    // Backups
    Route::get('/backup', [BackupController::class, 'index'])->middleware('can:view backup')->name('backup.index');
    Route::get('/backup/export', [BackupController::class, 'export'])->middleware('can:create backup')->name('backup.export');
    Route::post('/backup/import', [BackupController::class, 'import'])->middleware('can:create backup')->name('backup.import');
    Route::get('/backup/scheduled/{filename}/download', [BackupController::class, 'downloadScheduled'])->middleware('can:download backups')->name('backup.scheduled.download');
    Route::delete('/backup/scheduled/{filename}', [BackupController::class, 'deleteScheduled'])->middleware('can:create backup')->name('backup.scheduled.delete');

    // Time Off
    Route::get('/time-off', [TimeOffController::class, 'index'])->middleware('can:view time off')->name('time-off.index');
    Route::post('/time-off', [TimeOffController::class, 'store'])->middleware('can:view time off')->name('time-off.store');
    Route::patch('/time-off/{time_off}/approve', [TimeOffController::class, 'approve'])->middleware('can:approve time off')->name('time-off.approve');
    Route::patch('/time-off/{time_off}/reject', [TimeOffController::class, 'reject'])->middleware('can:reject time off')->name('time-off.reject');
    Route::patch('/time-off/{time_off}', [TimeOffController::class, 'update'])->middleware('can:edit time off')->name('time-off.update');
    Route::delete('/time-off/{time_off}', [TimeOffController::class, 'destroy'])->middleware('can:delete time off')->name('time-off.destroy');
});

require __DIR__.'/auth.php';
