<?php

use Illuminate\Support\Facades\Route;
use Modules\Sso\Presentation\Http\Controllers\SsoAuthorizeController;

Route::prefix('auth/sso')->group(function () {
    Route::get('authorize', [SsoAuthorizeController::class, 'authorize'])->name('sso.authorize');
});
