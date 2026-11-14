<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('page_infos')
            ->where('page_slug', 'trading-view')
            ->update([
                'page_slug' => 'signals',
                'title' => 'Signals',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('page_infos')
            ->where('page_slug', 'signals')
            ->update([
                'page_slug' => 'trading-view',
                'title' => 'TradingView',
            ]);
    }
};
