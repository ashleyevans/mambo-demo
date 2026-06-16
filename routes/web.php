<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BeaconController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionExportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::patch('dashboard/demo-refresh', [DashboardController::class, 'toggleDemoRefresh'])->name('dashboard.demo-refresh');
    Route::patch('dashboard/push-notifications', [DashboardController::class, 'togglePushNotifications'])->name('dashboard.push-notifications');
    Route::post('dashboard/sync', [DashboardController::class, 'sync'])->name('dashboard.sync');

    Route::get('transactions/export/csv', [TransactionExportController::class, 'csv'])->name('transactions.export.csv');
    Route::get('transactions/export/json', [TransactionExportController::class, 'json'])->name('transactions.export.json');
    Route::get('transactions/{transaction}/export/csv', [TransactionExportController::class, 'csvForTransaction'])->name('transactions.export.single.csv');
    Route::get('transactions/{transaction}/export/json', [TransactionExportController::class, 'jsonForTransaction'])->name('transactions.export.single.json');

    Route::get('beacons', [BeaconController::class, 'index'])->name('beacons.index');
    Route::post('beacons/demo-offer', [BeaconController::class, 'sendDemoOffer'])->name('beacons.demo-offer');

    Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/connect', [AccountController::class, 'connect'])->name('accounts.connect');
    Route::get('accounts/callback', [AccountController::class, 'callback'])->name('accounts.callback');
    Route::post('accounts/connections/{connection}/refresh', [AccountController::class, 'refresh'])->name('accounts.connections.refresh');
    Route::delete('accounts/connections/{connection}', [AccountController::class, 'destroy'])->name('accounts.connections.destroy');
});

require __DIR__.'/settings.php';
