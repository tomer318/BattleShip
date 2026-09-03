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
        Schema::create('leaderboards', function (Blueprint $table) {
            $table->id();
            $table->string('player_name')->default('Commander');
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'nightmare']);
            $table->integer('score');
            $table->integer('duration_seconds'); // Thời gian kết thúc trận
            $table->decimal('accuracy', 5, 2);   // % chính xác
            $table->integer('fleet_health');     // % máu còn lại của hạm đội
            $table->timestamps();

            $table->index(['difficulty', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboards');
    }
};
