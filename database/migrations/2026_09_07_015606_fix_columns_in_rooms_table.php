<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('status', 50)->default('waiting')->change();
            $table->string('current_turn', 50)->default('player1')->change();
            $table->string('winner', 50)->nullable()->change();
            $table->longText('p1_shots')->nullable()->change();
            $table->longText('p2_shots')->nullable()->change();
            $table->longText('p1_ships')->nullable()->change();
            $table->longText('p2_ships')->nullable()->change();
        });
    }

    public function down(): void
    {
        //
    }
};