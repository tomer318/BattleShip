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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('credits')->default(0); // Tiền $
            $table->json('inventory')->nullable();          // ['recon_sat' => 1, 'combat_airstrike' => 1, ...]
            $table->json('daily_purchases')->nullable();    // ['date' => '2026-09-04', 'items' => ['recon_sat' => 1]]
            $table->json('stats')->nullable();              // Điểm kỷ lục và số trận thắng từng độ khó
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
