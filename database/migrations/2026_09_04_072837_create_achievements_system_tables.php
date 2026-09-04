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
        // 1. Bảng lưu trữ cấu hình 30 thành tựu
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('description');
            $table->enum('category', ['pve', 'pvp']); 
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'nightmare']); 
            $table->unsignedInteger('reward_credits')->default(0);
            $table->unsignedInteger('reward_gems')->default(0);
            $table->timestamps();
        });

        // 2. Bảng theo dõi tiến độ và trạng thái nhận thưởng của Người chơi
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('achievement_code');
            $table->boolean('completed')->default(false);
            $table->boolean('claimed')->default(false); 
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
    }
};