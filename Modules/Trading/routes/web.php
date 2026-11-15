<?php

use Illuminate\Support\Facades\Route;
use Modules\Trading\Http\Controllers\MyfxbookOutlookEmbedController;

/*
|--------------------------------------------------------------------------
| Trading Web Routes
|--------------------------------------------------------------------------
|
| Filament hosts Trading pages under /admin/trading/* with a top sub-menu.
| Same-origin embeds keep third-party widget chrome adjustable (padding for
| Myfxbook hover tooltips) while staying behind the admin auth session.
|
*/

Route::middleware('auth')->group(function (): void {
    Route::get('/trading/embeds/myfxbook-outlook/{symbolId}', MyfxbookOutlookEmbedController::class)
        ->whereNumber('symbolId')
        ->name('trading.embeds.myfxbook-outlook');
});
