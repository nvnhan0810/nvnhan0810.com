<?php

use Illuminate\Support\Facades\Route;

/*
| API routes under /wallets/api/* (see config app.api_path_prefix).
*/

Route::get('/health', fn () => response()->json(['ok' => true]))->name('api.health');
