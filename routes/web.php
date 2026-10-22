<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ChurnAnalysisController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RiskRetentionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated application routes
|--------------------------------------------------------------------------
*/

// No 'verified' middleware: the User model does not implement MustVerifyEmail and
// the prototype has no email delivery, so verified accounts are seeded directly.
Route::middleware('auth')->group(function (): void {
    // Malformed identifiers never reach a controller: they resolve to a 404 rather
    // than a validation redirect, and they are rejected before any database query.
    Route::pattern('customerId', 'C[0-9]{5,8}');
    Route::pattern('planId', '[A-Za-z0-9_-]+');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customerId}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customerId}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('customers/{customerId}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::get('customers/{customerId}', [CustomerController::class, 'show'])->name('customers.show');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('plans/{planId}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{planId}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{planId}', [PlanController::class, 'destroy'])->name('plans.destroy');
    Route::get('plans/{planId}', [PlanController::class, 'show'])->name('plans.show');

    Route::get('churn-analysis', [ChurnAnalysisController::class, 'index'])->name('churn.index');

    Route::get('risk-retention', [RiskRetentionController::class, 'index'])->name('risk.index');
});
