<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prop_accounts', function (Blueprint $table) {
            $table->decimal('initial_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->nullable();
            $table->decimal('equity', 16, 2)->nullable();
            $table->decimal('day_start_balance', 16, 2)->nullable();
            $table->decimal('today_pnl', 16, 2)->nullable();
            $table->decimal('floating_pnl', 16, 2)->nullable();
            $table->timestamp('snapshot_at')->nullable();
        });

        Schema::table('prop_account_rules', function (Blueprint $table) {
            $table->string('stop_loss_mode', 32)->default('optional');
        });

        DB::table('prop_account_rules')
            ->where('stop_loss_required', true)
            ->update(['stop_loss_mode' => 'required_open']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prop_account_rules', function (Blueprint $table) {
            $table->dropColumn('stop_loss_mode');
        });

        Schema::table('prop_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'initial_balance',
                'current_balance',
                'equity',
                'day_start_balance',
                'today_pnl',
                'floating_pnl',
                'snapshot_at',
            ]);
        });
    }
};
