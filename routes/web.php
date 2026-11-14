<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageInfoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/admin/trading/trading-view', '/admin/trading/signals', 301);
Route::redirect('/page-info/trading-view', '/page-info/signals', 301);

Route::post('/locale/{locale}', [LocaleController::class, 'update'])
    ->whereIn('locale', ['en', 'ur'])
    ->name('locale.update');

Route::middleware('auth')->group(function (): void {
    Route::get('/page-info/{pageInfo}', [PageInfoController::class, 'show'])->name('page-info.show');
});
