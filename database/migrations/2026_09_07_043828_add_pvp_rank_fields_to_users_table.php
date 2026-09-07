<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('elo')->default(500)->after('gems'); // Mặc định Thủy thủ (500)
            $table->string('rank_tier', 50)->default('seaman')->after('elo'); // seaman, petty_officer, ensign, lieutenant, captain, fleet_admiral
            $table->unsignedInteger('pvp_wins')->default(0)->after('rank_tier');
            $table->unsignedInteger('pvp_losses')->default(0)->after('pvp_wins');
            $table->float('accuracy_rate')->default(30.0)->after('pvp_losses');
            $table->boolean('is_bot')->default(false)->after('accuracy_rate');
            $table->string('bot_difficulty', 20)->nullable()->after('is_bot'); // easy, medium, hard, nightmare
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'elo',
                'rank_tier',
                'pvp_wins',
                'pvp_losses',
                'accuracy_rate',
                'is_bot',
                'bot_difficulty'
            ]);
        });
    }
};