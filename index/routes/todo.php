<?php

use App\Http\Controllers\Admin\Todo\MatrixController;
use App\Http\Controllers\Admin\Todo\ProjectController;
use App\Http\Controllers\Admin\Todo\TodoController;
use App\Http\Controllers\Admin\Todo\WebPushController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/matrix', [MatrixController::class, 'index'])->name('matrix.index');
    Route::get('/matrix/stream', [MatrixController::class, 'stream'])->name('matrix.stream');
    Route::get('/matrix/pomodoro', [MatrixController::class, 'showPomodoro'])->name('matrix.pomodoro.show');
    Route::post('/matrix/pomodoro/start', [MatrixController::class, 'startPomodoro'])->name('matrix.pomodoro.start');
    Route::post('/matrix/pomodoro/pause', [MatrixController::class, 'pausePomodoro'])->name('matrix.pomodoro.pause');
    Route::post('/matrix/pomodoro/skip', [MatrixController::class, 'skipPomodoro'])->name('matrix.pomodoro.skip');
    Route::post('/matrix/pomodoro/reset', [MatrixController::class, 'resetPomodoro'])->name('matrix.pomodoro.reset');
    Route::put('/matrix/pomodoro/settings', [MatrixController::class, 'updatePomodoroSettings'])->name('matrix.pomodoro.settings');
    Route::patch('/matrix/pomodoro/active-todo', [MatrixController::class, 'updatePomodoroActiveTodo'])->name('matrix.pomodoro.active-todo');
    Route::post('/matrix/pomodoro/focus', [MatrixController::class, 'focusPomodoro'])->name('matrix.pomodoro.focus');
    Route::get('/matrix/web-push/vapid-public-key', [WebPushController::class, 'publicKey'])->name('matrix.web-push.vapid');
    Route::get('/matrix/web-push/status', [WebPushController::class, 'status'])->name('matrix.web-push.status');
    Route::post('/matrix/web-push/subscribe', [WebPushController::class, 'subscribe'])->name('matrix.web-push.subscribe');
    Route::delete('/matrix/web-push/subscribe', [WebPushController::class, 'unsubscribe'])->name('matrix.web-push.unsubscribe');
    Route::post('/matrix/web-push/presence', [WebPushController::class, 'presence'])->name('matrix.web-push.presence');
    Route::patch('/matrix/todos/{id}', [MatrixController::class, 'update'])->name('matrix.update');
    Route::patch('/matrix/todos/{id}/complete', [MatrixController::class, 'complete'])->name('matrix.complete');
    Route::post('/matrix/backlog/promote', [MatrixController::class, 'promoteBacklog'])->name('matrix.backlog.promote');
});

Route::middleware('auth')->prefix('todos')->name('todos.')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{id}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::get('/', [TodoController::class, 'index'])->name('index');
    Route::get('/create', [TodoController::class, 'create'])->name('create');
    Route::post('/', [TodoController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TodoController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TodoController::class, 'update'])->name('update');
    Route::delete('/{id}', [TodoController::class, 'destroy'])->name('destroy');
});
