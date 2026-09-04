<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->json('p1_ships')->nullable();
            $table->json('p2_ships')->nullable();
            $table->boolean('p1_ready')->default(false);
            $table->boolean('p2_ready')->default(false);
            $table->string('current_turn')->default('player1'); // 'player1' hoặc 'player2'
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['p1_ships', 'p2_ships', 'p1_ready', 'p2_ready', 'current_turn']);
        });
    }
};