<?php

use Illuminate\Support\Facades\Route;

/*
| API routes under /api/* (satellite domain root).
*/

Route::get('/health', fn () => response()->json(['ok' => true]))->name('api.health');
