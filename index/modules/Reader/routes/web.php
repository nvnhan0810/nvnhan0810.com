<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reader\Presentation\Http\Controllers\Admin\AdminCollectionController;
use Modules\Reader\Presentation\Http\Controllers\Admin\AdminDocumentController;
use Modules\Reader\Presentation\Http\Controllers\Admin\AdminTrashController;

Route::middleware('auth')->prefix('admin/reader')->name('admin.reader.')->group(function (): void {
    Route::redirect('/', '/admin/reader/documents')->name('index');

    Route::get('/documents', [AdminDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [AdminDocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [AdminDocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{id}/file', [AdminDocumentController::class, 'file'])->name('documents.file');
    Route::get('/documents/{id}/edit', [AdminDocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/documents/{id}', [AdminDocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{id}', [AdminDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{id}', [AdminDocumentController::class, 'show'])->name('documents.show');

    Route::get('/collections', [AdminCollectionController::class, 'index'])->name('collections.index');
    Route::get('/collections/create', [AdminCollectionController::class, 'create'])->name('collections.create');
    Route::post('/collections', [AdminCollectionController::class, 'store'])->name('collections.store');
    Route::get('/collections/{id}/edit', [AdminCollectionController::class, 'edit'])->name('collections.edit');
    Route::put('/collections/{id}', [AdminCollectionController::class, 'update'])->name('collections.update');
    Route::delete('/collections/{id}', [AdminCollectionController::class, 'destroy'])->name('collections.destroy');

    Route::get('/trash', [AdminTrashController::class, 'index'])->name('trash.index');
    Route::post('/trash/{id}/restore', [AdminTrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/{id}', [AdminTrashController::class, 'destroy'])->name('trash.destroy');
    Route::delete('/trash', [AdminTrashController::class, 'empty'])->name('trash.empty');
});
