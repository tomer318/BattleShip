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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code')->unique(); 
            $table->unsignedBigInteger('player1_id'); 
            $table->unsignedBigInteger('player2_id')->nullable(); 
            $table->unsignedBigInteger('game_id')->nullable(); 
            $table->enum('status', ['waiting', 'setup', 'playing', 'ended'])->default('waiting');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
