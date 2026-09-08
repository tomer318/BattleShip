<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Tạo tài khoản Test User an toàn (dùng firstOrCreate để không bị duplicate và không dùng fake())
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'credits' => 1000,
                'gems' => 50,
                'elo' => 500,
                'rank_tier' => 'seaman',
                'is_bot' => false,
            ]
        );

        // 2. Kích hoạt nạp toàn bộ dàn Bot PvP và Rank
        $this->call([
            PvpRankedBotsSeeder::class,
        ]);
    }
}