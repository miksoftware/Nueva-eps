<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EpsCredentialController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Consultas (all authenticated users)
    Route::get('/consultas', [ConsultaController::class, 'index'])->name('consultas.index');
    Route::post('/consultas/save-result', [ConsultaController::class, 'saveResult'])->name('consultas.saveResult');
    Route::get('/consultas/{lote}/status', [ConsultaController::class, 'loteStatus'])->name('consultas.loteStatus');
    Route::get('/consultas/{lote}/export', [ConsultaController::class, 'export'])->name('consultas.export');
    Route::get('/consultas/search/{cedula}', [ConsultaController::class, 'search'])->name('consultas.search');

    // Admin only
    Route::middleware('admin')->group(function () {
        // User CRUD
        Route::resource('users', UserController::class)->except(['show']);

        // EPS Credentials
        Route::get('/eps/credentials', [EpsCredentialController::class, 'index'])->name('eps.credentials');
        Route::post('/eps/save-token', [EpsCredentialController::class, 'saveToken'])->name('eps.saveToken');
        Route::post('/eps/logout', [EpsCredentialController::class, 'logout'])->name('eps.logout');

        // CSV upload (admin only)
        Route::post('/consultas/upload', [ConsultaController::class, 'upload'])->name('consultas.upload');
    });
});
