<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskReportController;
use App\Http\Controllers\VideoTaskController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('users', UserController::class)->middleware('can:manage users');
    Route::get('/dashboard', function () {return Inertia::render('Dashboard');})->name('dashboard');
    Route::resource('roles', RoleController::class)->middleware('can:manage roles');
    Route::get('/task-reports', [TaskReportController::class, 'index'])->middleware('can:view reports');
    Route::resource('video-tasks', VideoTaskController::class)->middleware('can:manage tasks');
});

require __DIR__.'/auth.php';
