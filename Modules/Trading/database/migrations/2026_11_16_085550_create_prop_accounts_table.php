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
        Schema::create('prop_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('firm_name');
            $table->string('account_number');
            $table->string('platform', 32);
            $table->string('status', 32);
            $table->string('risk_category', 32);
            $table->timestamps();

            $table->unique(['firm_name', 'account_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prop_accounts');
    }
};
