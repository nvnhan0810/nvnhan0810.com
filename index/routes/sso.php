<?php

use Illuminate\Support\Facades\Route;
use Modules\Sso\Presentation\Http\Controllers\AdminSsoClientController;
use Modules\Sso\Presentation\Http\Controllers\SsoAuthorizeController;

Route::prefix('auth/sso')->group(function () {
    Route::get('authorize', [SsoAuthorizeController::class, 'authorize'])->name('sso.authorize');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('sso-clients', AdminSsoClientController::class)->except(['show']);
});
