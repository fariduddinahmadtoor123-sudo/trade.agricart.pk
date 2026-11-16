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
        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('mt5_ticket')->nullable()->unique();
            $table->decimal('floating_pnl', 18, 2)->nullable();
            $table->string('bridge_message', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->dropUnique(['mt5_ticket']);
            $table->dropColumn(['mt5_ticket', 'floating_pnl', 'bridge_message']);
        });
    }
};
