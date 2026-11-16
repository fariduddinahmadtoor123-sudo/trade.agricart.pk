<?php

use Illuminate\Support\Facades\Route;
use Modules\Trading\Http\Controllers\MarketDataController;
use Modules\Trading\Http\Controllers\Mt5BridgeController;

Route::middleware(['auth:sanctum'])->prefix('v1/trading')->name('trading.')->group(function (): void {
    Route::get('market-data', [MarketDataController::class, 'index'])->name('market-data.index');
    Route::post('market-data', [MarketDataController::class, 'store'])->name('market-data.store');
    Route::post('mt5/sync', [Mt5BridgeController::class, 'sync'])->name('mt5.sync');
    Route::get('mt5/orders/pending', [Mt5BridgeController::class, 'pending'])->name('mt5.orders.pending');
    Route::post('mt5/orders/{order}/execution', [Mt5BridgeController::class, 'execution'])->name('mt5.orders.execution');
});
