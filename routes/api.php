<?php

use App\Http\Controllers\BeaconController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('beacon/enter', [BeaconController::class, 'enter'])->name('beacon.enter');
Route::post('beacon/exit', [BeaconController::class, 'exit'])->name('beacon.exit');
