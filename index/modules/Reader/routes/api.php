<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reader\Presentation\Http\Controllers\AnnotationController;
use Modules\Reader\Presentation\Http\Controllers\AuthController;
use Modules\Reader\Presentation\Http\Controllers\CollectionController;
use Modules\Reader\Presentation\Http\Controllers\DocumentController;
use Modules\Reader\Presentation\Http\Controllers\ProgressController;
use Modules\Reader\Presentation\Http\Controllers\SyncController;
use Modules\Reader\Presentation\Http\Controllers\TrashController;

// Under /api so hosts with Basic auth (dev) still reach the app; mobile uses /api/v1/*.
Route::prefix('api/v1')->group(function (): void {
    Route::post('/auth/sso/exchange', [AuthController::class, 'exchange']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::get('/favorites', [DocumentController::class, 'favorites']);
        Route::get('/documents/{id}', [DocumentController::class, 'show']);
        Route::put('/documents/{id}', [DocumentController::class, 'update']);
        Route::put('/documents/{id}/favorite', [DocumentController::class, 'setFavorite']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
        Route::post('/documents/{id}/file', [DocumentController::class, 'uploadFile']);
        Route::get('/documents/{id}/file', [DocumentController::class, 'downloadFile']);
        Route::post('/documents/{id}/thumbnail', [DocumentController::class, 'uploadThumbnail']);
        Route::get('/documents/{id}/thumbnail', [DocumentController::class, 'downloadThumbnail']);

        Route::get('/trash', [TrashController::class, 'index']);
        Route::delete('/trash', [TrashController::class, 'empty']);
        Route::post('/trash/{id}/restore', [TrashController::class, 'restore']);
        Route::delete('/trash/{id}', [TrashController::class, 'destroy']);

        Route::get('/collections', [CollectionController::class, 'index']);
        Route::post('/collections', [CollectionController::class, 'store']);
        Route::get('/collections/{id}', [CollectionController::class, 'show']);
        Route::put('/collections/{id}', [CollectionController::class, 'update']);
        Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);
        Route::get('/collections/{id}/documents', [CollectionController::class, 'documents']);
        Route::post('/collections/{id}/documents', [CollectionController::class, 'addDocument']);
        Route::delete('/collections/{id}/documents/{documentId}', [CollectionController::class, 'removeDocument']);

        Route::get('/documents/{id}/annotations', [AnnotationController::class, 'index']);
        Route::get('/documents/{id}/annotations/{pageIndex}', [AnnotationController::class, 'show'])
            ->whereNumber('pageIndex');
        Route::put('/documents/{id}/annotations/{pageIndex}', [AnnotationController::class, 'upsert'])
            ->whereNumber('pageIndex');

        Route::get('/documents/{id}/progress', [ProgressController::class, 'show']);
        Route::put('/documents/{id}/progress', [ProgressController::class, 'upsert']);

        Route::get('/sync/changes', [SyncController::class, 'changes']);
    });
});

