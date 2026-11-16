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
        Schema::table('prop_accounts', function (Blueprint $table) {
            $table->string('model_type', 32)->nullable();
            $table->unsignedTinyInteger('phase')->default(1);
        });

        Schema::table('prop_account_rules', function (Blueprint $table) {
            $table->decimal('phase_two_profit_target_percent', 8, 2)->nullable();
            $table->string('max_drawdown_basis', 16)->default('static');
            $table->string('payout_style', 32)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prop_account_rules', function (Blueprint $table) {
            $table->dropColumn([
                'phase_two_profit_target_percent',
                'max_drawdown_basis',
                'payout_style',
            ]);
        });

        Schema::table('prop_accounts', function (Blueprint $table) {
            $table->dropColumn(['model_type', 'phase']);
        });
    }
};
