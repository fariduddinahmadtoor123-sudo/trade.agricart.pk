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
        Schema::create('mt5_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('login', 64)->unique();
            $table->text('password')->nullable();
            $table->string('server', 128)->nullable();
            $table->string('company', 128)->nullable();
            $table->string('holder_name', 128)->nullable();
            $table->decimal('balance', 18, 2)->nullable();
            $table->decimal('equity', 18, 2)->nullable();
            $table->decimal('today_pnl', 18, 2)->nullable();
            $table->decimal('floating_pnl', 18, 2)->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->dropForeign(['prop_account_id']);
        });

        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('prop_account_id')->nullable()->change();
            $table->foreign('prop_account_id')->references('id')->on('prop_accounts')->cascadeOnDelete();
            $table->foreignId('mt5_account_id')->nullable()->after('prop_account_id')->constrained('mt5_accounts')->nullOnDelete();
            $table->index(['mt5_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->dropIndex(['mt5_account_id', 'status']);
            $table->dropConstrainedForeignId('mt5_account_id');
            $table->dropForeign(['prop_account_id']);
        });

        Schema::table('terminal_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('prop_account_id')->nullable(false)->change();
            $table->foreign('prop_account_id')->references('id')->on('prop_accounts')->cascadeOnDelete();
        });

        Schema::dropIfExists('mt5_accounts');
    }
};
