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
        Schema::create('terminal_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prop_account_id')->constrained('prop_accounts')->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('side', 8);
            $table->decimal('lots', 10, 2);
            $table->decimal('stop_loss', 18, 8)->nullable();
            $table->decimal('take_profit', 18, 8)->nullable();
            $table->decimal('entry_price', 18, 8);
            $table->decimal('exit_price', 18, 8)->nullable();
            $table->string('status', 16);
            $table->decimal('realized_pnl', 18, 2)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['prop_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_orders');
    }
};
