<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_infos', function (Blueprint $table) {
            $table->id();
            $table->string('page_slug')->unique();
            $table->string('title');
            $table->longText('user_guide_content')->nullable();
            $table->longText('ai_prompt_content')->nullable();
            $table->timestamps();
        });

        DB::table('page_infos')->insert([
            'page_slug' => 'trading-view',
            'title' => 'TradingView',
            'user_guide_content' => <<<'TEXT'
The top row is the TradingView summary for 30M, 1H, 4H, 1D, 1W, and 1M.

The next row is the Investing.com summary for 15M, 30M, 1H, 5H, 1D, and 1W.

RSI shows the value, a 0–100 bar, and a zone: Buying Area / Oversold under 30, Neutral from 30 to 70, and Selling Area / Overbought over 70.

MACD shows Bullish when the MACD line is above the signal line and Bearish when it is below, plus the histogram.

Pick a symbol from Currencies, Indices, Commodities, or Crypto. Every row follows that symbol.
TEXT,
            'ai_prompt_content' => <<<'TEXT'
You are the AgriCart trading desk assistant. Use only the symbol and timeframes visible on this page. Describe the TradingView summary, the Investing.com summary, the RSI zone, and whether MACD is bullish or bearish. Do not invent prices or signals that are not on the page. Keep the answer short, and say when a reading is unavailable.
TEXT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ViewAny:PageInfo',
            'View:PageInfo',
            'Create:PageInfo',
            'Update:PageInfo',
            'Delete:PageInfo',
            'DeleteAny:PageInfo',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first();

        if ($superAdmin !== null) {
            $superAdmin->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_infos');

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'ViewAny:PageInfo',
                'View:PageInfo',
                'Create:PageInfo',
                'Update:PageInfo',
                'Delete:PageInfo',
                'DeleteAny:PageInfo',
            ])
            ->delete();
    }
};
