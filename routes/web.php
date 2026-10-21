<?php

use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\AI\AudioController;
use App\Http\Controllers\AI\CopyController;
use App\Http\Controllers\AI\PhrasesController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtraTaskController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\InstagramController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportHistoryController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskReportController;
use App\Http\Controllers\TaskShareController;
use App\Http\Controllers\TimeOffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\VideoTaskController;
use App\Http\Controllers\YoutubeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('welcome');

Route::middleware('auth')->get('/subscription/blocked', function () {
    $user = auth()->user();
    $org = $user?->organization;

    return Inertia::render('Subscription/Blocked', [
        'statusLabel' => $org?->isAccessBlocked() ? ($org->statusLabels()[$org->effectiveStatus()] ?? $org->status) : null,
        'plan' => $org?->plan,
        'trialEndsAt' => $org?->trial_ends_at?->format('d/m/Y'),
    ]);
})->name('subscription.blocked');

Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('can:view dashboard')->name('dashboard');
    Route::get('/dashboard/weekly-summary', [DashboardController::class, 'weeklySummary'])->middleware(['can:view dashboard', 'throttle:5,1'])->name('dashboard.weekly-summary');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('/notifications/unread', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:60,1')->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:60,1')->name('notifications.readAll');
    Route::post('/notifications/clear-all', [NotificationController::class, 'clearAll'])->middleware('throttle:60,1')->name('notifications.clearAll');

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
        Route::get('/planning/search', [PlanningController::class, 'search'])->name('planning.search');
        Route::get('/planning/occupied-blocks', [PlanningController::class, 'occupiedBlocks'])->name('planning.occupied-blocks');
        Route::redirect('/video-tasks', '/planning');
    });
    Route::get('/tasks/create', [VideoTaskController::class, 'create'])->middleware('can:create planning')->name('tasks.create');
    Route::post('/tasks', [VideoTaskController::class, 'store'])->middleware('can:create planning')->name('tasks.store');
    Route::get('/tasks/pending', [VideoTaskController::class, 'pending'])->middleware('can:view planning')->name('tasks.pending.index');
    Route::get('/tasks/today-summary', [VideoTaskController::class, 'todaySummary'])->middleware('can:view planning')->name('tasks.today-summary');
    Route::get('/tasks/today-tasks', [VideoTaskController::class, 'todayTasks'])->middleware('can:view planning')->name('tasks.today-tasks');
    Route::get('/tasks/translate-candidates', [VideoTaskController::class, 'translateCandidates'])->middleware('can:view planning')->name('tasks.translate-candidates');
    Route::get('/tasks/extra', [ExtraTaskController::class, 'index'])->middleware('can:view planning')->name('tasks.extra.index');
    Route::post('/tasks/extra', [ExtraTaskController::class, 'store'])->middleware('can:create planning')->name('tasks.extra.store');
    Route::patch('/tasks/extra/{extra_task}', [ExtraTaskController::class, 'update'])->middleware('can:edit planning')->name('tasks.extra.update');
    Route::delete('/tasks/extra/{extra_task}', [ExtraTaskController::class, 'destroy'])->middleware('can:delete planning')->name('tasks.extra.destroy');
    Route::get('/tasks/{video_task}', [VideoTaskController::class, 'show'])->middleware('can:view planning')->name('tasks.show');
    Route::post('/tasks/generate-copy', [VideoTaskController::class, 'generateCopy'])->middleware(['can:generate ai', 'quota:tasks.generate-copy', 'throttle:10,1'])->name('tasks.generate-copy');
    Route::post('/tasks/translate', [VideoTaskController::class, 'translate'])->middleware(['can:generate ai', 'quota:tasks.translate', 'throttle:10,1'])->name('tasks.translate');
    Route::post('/tasks/{video_task}/translate', [VideoTaskController::class, 'translateTask'])->middleware(['can:create planning', 'can:generate ai', 'quota:tasks.translate-task', 'throttle:10,1'])->name('tasks.translate-task');
    Route::post('/tasks/{video_task}/translate-content', [VideoTaskController::class, 'translateContent'])->middleware(['can:create planning', 'can:generate ai', 'quota:tasks.translate-content', 'throttle:10,1'])->name('tasks.translate-content');
    Route::post('/tasks/generate-title-thumbnails', [VideoTaskController::class, 'generateTitlesAndThumbnails'])->middleware(['can:generate ai', 'quota:tasks.generate-title-thumbnails', 'throttle:10,1'])->name('tasks.generate-title-thumbnails');
    Route::get('/tasks/{video_task}/edit', [VideoTaskController::class, 'edit'])->middleware('can:edit planning')->name('tasks.edit');
    Route::put('/tasks/{video_task}', [VideoTaskController::class, 'update'])->middleware('can:edit planning')->name('tasks.update');
    Route::patch('/tasks/{video_task}/status', [VideoTaskController::class, 'updateStatus'])->middleware('can:edit planning')->name('tasks.status');
    Route::post('/tasks/{video_task}/sessions', [VideoTaskController::class, 'storeSession'])->middleware('can:edit planning')->name('tasks.sessions.store');
    Route::patch('/tasks/{video_task}/sessions/{session}', [VideoTaskController::class, 'updateSession'])->middleware('can:edit planning')->name('tasks.sessions.update');
    Route::delete('/tasks/{video_task}/sessions/{session}', [VideoTaskController::class, 'destroySession'])->middleware('can:delete planning')->name('tasks.sessions.destroy');
    Route::post('/tasks/{video_task}/move', [VideoTaskController::class, 'move'])->middleware('can:edit planning')->name('tasks.move');
    Route::patch('/tasks/{video_task}/pending', [VideoTaskController::class, 'moveToPending'])->middleware('can:edit planning')->name('tasks.pending');
    Route::patch('/tasks/{video_task}/restore', [VideoTaskController::class, 'restorePending'])->middleware('can:edit planning')->name('tasks.restore');
    Route::delete('/tasks/{video_task}', [VideoTaskController::class, 'destroy'])->middleware('can:delete planning')->name('tasks.destroy');
    // Backward compatibility redirects
    Route::redirect('/video-tasks/create', '/tasks/create');
    Route::redirect('/video-tasks/pending', '/tasks/pending');
    Route::redirect('/video-tasks/{video_task}', '/tasks/{video_task}');
    Route::redirect('/video-tasks/{video_task}/edit', '/tasks/{video_task}/edit');
    Route::redirect('/extra-tasks', '/tasks/extra');
    Route::get('/planning/observation', [PlanningController::class, 'getObservation'])->middleware('can:view planning')->name('planning.observation.get');
    Route::post('/planning/observation', [PlanningController::class, 'saveObservation'])->middleware('can:view planning')->name('planning.observation.save');

    // Ideas
    Route::get('/ideas', [IdeaController::class, 'index'])->middleware('can:view ideas')->name('ideas.index');
    Route::post('/ideas', [IdeaController::class, 'store'])->middleware('can:create ideas')->name('ideas.store');
    Route::post('/ideas/import', [IdeaController::class, 'import'])->middleware('can:import ideas')->name('ideas.import');
    Route::post('/ideas/generate', [IdeaController::class, 'generate'])->middleware(['can:create ideas', 'can:generate ai', 'quota:ideas.generate', 'throttle:10,1'])->name('ideas.generate');
    Route::get('/ideas/export', [IdeaController::class, 'export'])->middleware('can:export ideas')->name('ideas.export');
    Route::post('/ideas/bulk-update', [IdeaController::class, 'bulkUpdate'])->middleware('can:edit ideas')->name('ideas.bulk-update');
    Route::patch('/ideas/{idea}/used', [IdeaController::class, 'toggleUsed'])->middleware('can:edit ideas')->name('ideas.toggle-used');
    Route::patch('/ideas/{idea}', [IdeaController::class, 'update'])->middleware('can:edit ideas')->name('ideas.update');
    Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->middleware('can:delete ideas')->name('ideas.destroy');

    // Reports
    Route::get('/task-reports/pdf', [TaskReportController::class, 'exportPdf'])->name('reports.pdf');
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
    Route::post('/company/switch', [CompanyController::class, 'switchCompany'])->middleware('can:view empresa')->name('company.switch');

    // Company channels & invites (keep specific routes before wildcards)
    Route::post('/company/channels', [CompanyController::class, 'storeChannel'])->middleware('can:create empresa');
    Route::post('/company/channels/{channel}', [CompanyController::class, 'updateChannel'])->middleware('can:edit empresa');
    Route::post('/company/channels/{channel}/delete', [CompanyController::class, 'destroyChannel'])->middleware('can:delete empresa');
    Route::post('/company/generate-invite', [CompanyController::class, 'generateInviteCode'])->middleware('can:edit empresa');

    // Facebook (social connections)
    Route::get('/facebook', [FacebookController::class, 'index'])->middleware('can:view empresa')->name('facebook.index');
    Route::get('/facebook/comments', [FacebookController::class, 'comments'])->middleware('can:view empresa')->name('facebook.comments');
    Route::post('/facebook/comments/reply', [FacebookController::class, 'reply'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.reply');
    Route::post('/facebook/comments/hide', [FacebookController::class, 'hideComment'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.comments.hide');
    Route::post('/facebook/comments/delete', [FacebookController::class, 'deleteComment'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.comments.delete');
    Route::get('/facebook/messages', [FacebookController::class, 'messages'])->middleware('can:view empresa')->name('facebook.messages');
    Route::post('/facebook/messages/send', [FacebookController::class, 'send'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.send');
    Route::post('/facebook/publish', [FacebookController::class, 'publish'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.publish');
    Route::post('/facebook/posts/update', [FacebookController::class, 'updatePost'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.posts.update');
    Route::post('/facebook/posts/delete', [FacebookController::class, 'deletePost'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('facebook.posts.delete');
    Route::post('/company/facebook/connect', [FacebookController::class, 'connect'])->middleware('can:edit empresa')->name('company.facebook.connect');
    Route::post('/company/facebook/disconnect', [FacebookController::class, 'disconnect'])->middleware('can:edit empresa')->name('company.facebook.disconnect');

    // Instagram (rides on the connected Facebook page token)
    Route::get('/instagram', [InstagramController::class, 'index'])->middleware('can:view empresa')->name('instagram.index');
    Route::get('/instagram/comments', [InstagramController::class, 'comments'])->middleware('can:view empresa')->name('instagram.comments');
    Route::post('/instagram/comments/reply', [InstagramController::class, 'reply'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.reply');
    Route::post('/instagram/comments/hide', [InstagramController::class, 'hideComment'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.comments.hide');
    Route::post('/instagram/comments/delete', [InstagramController::class, 'deleteComment'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.comments.delete');
    Route::post('/instagram/comments/like', [InstagramController::class, 'likeComment'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.comments.like');
    Route::get('/instagram/messages', [InstagramController::class, 'messages'])->middleware('can:view empresa')->name('instagram.messages');
    Route::post('/instagram/messages/send', [InstagramController::class, 'sendMessage'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.messages.send');
    Route::post('/instagram/publish', [InstagramController::class, 'publish'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.publish');
    Route::post('/instagram/media/delete', [InstagramController::class, 'deleteMedia'])->middleware('can:edit empresa')->middleware('throttle:10,1')->name('instagram.media.delete');

    // User Settings (per-user preferences)
    Route::get('/settings', [UserSettingsController::class, 'index'])->middleware('can:view configuracion')->name('settings.index');
    Route::put('/settings', [UserSettingsController::class, 'update'])->middleware('can:view configuracion')->name('settings.update');

    // YouTube
    Route::get('/youtube', [YoutubeController::class, 'index'])->middleware('can:view youtube')->name('youtube.index');
    Route::get('/youtube/comments', [YoutubeController::class, 'comments'])->middleware('can:view youtube')->name('youtube.comments');

    // AI Generator
    Route::get('/ai', [AIController::class, 'index'])->middleware('can:generate ai')->name('ai.index');
    Route::post('/ai/generate', [AIController::class, 'generateScript'])->middleware(['can:generate ai', 'quota:ai.generate', 'throttle:10,1'])->name('ai.generate');
    Route::post('/ai/audio', [AudioController::class, 'generate'])->middleware(['can:generate ai', 'quota:ai.audio', 'throttle:10,1'])->name('ai.audio');
    Route::post('/ai/copy', [CopyController::class, 'generate'])->middleware(['can:generate ai', 'quota:ai.copy', 'throttle:10,1'])->name('ai.copy');
    Route::post('/ai/phrases', [PhrasesController::class, 'generate'])->middleware(['can:generate ai', 'quota:ai.phrases', 'throttle:10,1'])->name('ai.phrases');
    Route::post('/ai/copy-phrases', [PhrasesController::class, 'generateWithCopy'])->middleware(['can:generate ai', 'quota:ai.copy-phrases', 'throttle:10,1'])->name('ai.copy-phrases');
    Route::post('/ai/create-task', [AIController::class, 'createTask'])->middleware(['can:generate ai', 'quota:ai.create-task', 'throttle:10,1'])->name('ai.create-task');

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

    // Time Off
    Route::get('/time-off', [TimeOffController::class, 'index'])->middleware('can:view time off')->name('time-off.index');
    Route::post('/time-off', [TimeOffController::class, 'store'])->middleware('can:view time off')->name('time-off.store');
    Route::patch('/time-off/{time_off}/approve', [TimeOffController::class, 'approve'])->middleware('can:approve time off')->name('time-off.approve');
    Route::patch('/time-off/{time_off}/reject', [TimeOffController::class, 'reject'])->middleware('can:reject time off')->name('time-off.reject');
    Route::patch('/time-off/{time_off}', [TimeOffController::class, 'update'])->middleware('can:edit time off')->name('time-off.update');
    Route::delete('/time-off/{time_off}', [TimeOffController::class, 'destroy'])->middleware('can:delete time off')->name('time-off.destroy');

    // Task Sharing
    Route::get('/task-shares/users', [TaskShareController::class, 'orgUsers'])->middleware('can:view planning')->name('task-shares.users');
    Route::post('/task-shares', [TaskShareController::class, 'update'])->middleware('can:edit planning')->name('task-shares.update');
    Route::post('/task-shares/unshare', [TaskShareController::class, 'unshare'])->middleware('can:edit planning')->name('task-shares.unshare');
    Route::post('/task-shares/{taskShare}/accept', [TaskShareController::class, 'accept'])->middleware('can:view planning')->name('task-shares.accept');
    Route::post('/task-shares/{taskShare}/reject', [TaskShareController::class, 'reject'])->middleware('can:view planning')->name('task-shares.reject');

    // Admin console (Super Admin only)
    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin')->group(function () {
        Route::get('/organizations', [AdminOrganizationController::class, 'index'])->name('organizations.index');
        Route::patch('/organizations/{organization}/suspend', [AdminOrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::patch('/organizations/{organization}/activate', [AdminOrganizationController::class, 'activate'])->name('organizations.activate');
        Route::patch('/organizations/{organization}/plan', [AdminOrganizationController::class, 'setPlan'])->name('organizations.plan');
    });
});

require __DIR__.'/auth.php';
