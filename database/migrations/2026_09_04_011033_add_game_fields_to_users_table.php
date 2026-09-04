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
            if (!Schema::hasColumn('users', 'credits')) {
                $table->unsignedInteger('credits')->default(0)->after('password');
            }
            if (!Schema::hasColumn('users', 'inventory')) {
                $table->json('inventory')->nullable()->after('credits');
            }
            if (!Schema::hasColumn('users', 'daily_purchases')) {
                $table->json('daily_purchases')->nullable()->after('inventory');
            }
            if (!Schema::hasColumn('users', 'stats')) {
                $table->json('stats')->nullable()->after('daily_purchases');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            foreach (['credits', 'inventory', 'daily_purchases', 'stats'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $columns[] = $col;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};