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
        Schema::create('trading_logs', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 32)->index();
            $table->decimal('bid', 18, 8)->nullable();
            $table->decimal('ask', 18, 8)->nullable();
            $table->decimal('last', 18, 8)->nullable();
            $table->decimal('volume', 18, 8)->nullable();
            $table->string('source', 64)->default('python-bridge')->index();
            $table->json('payload')->nullable();
            $table->timestamp('feed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['symbol', 'feed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_logs');
    }
};
