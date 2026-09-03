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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->enum('mode', ['pve', 'pvp'])->default('pve');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'nightmare'])->default('easy');
            $table->enum('status', ['setup', 'playing', 'won', 'lost'])->default('setup');
            $table->string('current_turn')->default('player');
            
            $table->json('player_ships')->nullable();
            $table->json('player_shots')->nullable();
            $table->json('bot_ships')->nullable();
            $table->json('bot_shots')->nullable();
            $table->json('bot_memory')->nullable();

            // Cột thời gian tính điểm còn thiếu
            $table->timestamp('started_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
