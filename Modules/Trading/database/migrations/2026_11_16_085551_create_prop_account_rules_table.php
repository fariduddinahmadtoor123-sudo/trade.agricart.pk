<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prop_account_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prop_account_id')->constrained('prop_accounts')->cascadeOnDelete();
            $table->string('source', 16)->default('manual');
            $table->decimal('profit_target_percent', 8, 2);
            $table->decimal('daily_drawdown_percent', 8, 2);
            $table->string('daily_drawdown_type', 16);
            $table->decimal('max_total_drawdown_percent', 8, 2);
            $table->decimal('max_loss_per_trade_percent', 8, 2);
            $table->unsignedSmallInteger('minimum_trading_days');
            $table->string('leverage', 32);
            $table->boolean('stop_loss_required')->default(false);
            $table->text('stop_loss_notes')->nullable();
            $table->timestamps();

            $table->unique('prop_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prop_account_rules');
    }
};
