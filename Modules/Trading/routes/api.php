<?php

use Illuminate\Support\Facades\Route;
use Modules\Trading\Http\Controllers\MarketDataController;

Route::middleware(['auth:sanctum'])->prefix('v1/trading')->name('trading.')->group(function (): void {
    Route::get('market-data', [MarketDataController::class, 'index'])->name('market-data.index');
    Route::post('market-data', [MarketDataController::class, 'store'])->name('market-data.store');
});
