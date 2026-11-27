<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SwapController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [SessionController::class, 'login'])->name('login');
    Route::post('/masuk', [SessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [InformationController::class, 'dashboard'])->name('dashboard');
    Route::post('/keluar', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/password', [SessionController::class, 'password'])->name('password.edit');
    Route::post('/password', [SessionController::class, 'update'])->name('password.update');
    Route::get('/jadwal', [ScheduleController::class, 'index'])->name('schedule');
    Route::post('/jadwal', [ScheduleController::class, 'store']);
    Route::get('/pertukaran/{id?}', [SwapController::class, 'index'])->whereNumber('id');
    Route::post('/pertukaran', [SwapController::class, 'submit']);
    Route::post('/pertukaran/{id}', [SwapController::class, 'act'])->whereNumber('id');
    Route::get('/notifikasi', [InformationController::class, 'notifications']);
    Route::post('/notifikasi/{id?}', [InformationController::class, 'read'])->whereNumber('id');
    Route::get('/riwayat', [InformationController::class, 'history']);
    Route::post('/konfirmasi/{id}', [InformationController::class, 'acknowledge'])->whereNumber('id');
    Route::get('/admin', [AdminController::class, 'index']);
    Route::post('/admin/master/{type}/{id?}', [AdminController::class, 'master'])->whereIn('type', ['outlets', 'positions'])->whereNumber('id');
    Route::post('/admin/akun/{id?}', [AdminController::class, 'user'])->whereNumber('id');
    Route::post('/admin/akun/{id}/{action}', [AdminController::class, 'accountAction'])->whereNumber('id')->whereIn('action', ['disable', 'reset']);
    Route::post('/admin/penugasan', [AdminController::class, 'assign']);
});
