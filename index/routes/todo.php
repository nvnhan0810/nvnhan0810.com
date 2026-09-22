<?php

use App\Http\Controllers\Admin\Todo\MatrixController;
use App\Http\Controllers\Admin\Todo\ProjectController;
use App\Http\Controllers\Admin\Todo\TodoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/matrix', [MatrixController::class, 'index'])->name('matrix.index');
    Route::get('/matrix/stream', [MatrixController::class, 'stream'])->name('matrix.stream');
    Route::patch('/matrix/todos/{id}', [MatrixController::class, 'update'])->name('matrix.update');
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
