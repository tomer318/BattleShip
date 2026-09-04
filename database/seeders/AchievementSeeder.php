<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AchievementSeeder extends Seeder
{
     /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $achievements = [
            // === 15 THÀNH TỰU BOT (PVE) ===
            ['code' => 'pve_first_shot', 'title' => 'Phát Đạn Đầu Tiên', 'description' => 'Khai hỏa phát pháo đầu tiên vào vùng biển đối phương.', 'category' => 'pve', 'difficulty' => 'easy', 'reward_credits' => 50, 'reward_gems' => 2],
            ['code' => 'pve_first_hit', 'title' => 'Hỏa Lực Khai Mào', 'description' => 'Bắn trúng ô tàu đối phương đầu tiên.', 'category' => 'pve', 'difficulty' => 'easy', 'reward_credits' => 100, 'reward_gems' => 3],
            ['code' => 'pve_first_sunk', 'title' => 'Mồi Cho Cá Biển', 'description' => 'Đánh chìm thành công 1 con tàu của Bot.', 'category' => 'pve', 'difficulty' => 'easy', 'reward_credits' => 150, 'reward_gems' => 4],
            ['code' => 'pve_beat_easy', 'title' => 'Hạ Gục Tân Binh', 'description' => 'Đánh bại Bot cấp độ Dễ.', 'category' => 'pve', 'difficulty' => 'easy', 'reward_credits' => 200, 'reward_gems' => 5],
            ['code' => 'pve_beat_medium', 'title' => 'Thợ Săn Hải Tặc', 'description' => 'Đánh bại Bot cấp độ Trung Bình.', 'category' => 'pve', 'difficulty' => 'medium', 'reward_credits' => 350, 'reward_gems' => 8],
            ['code' => 'pve_beat_hard', 'title' => 'Chiến Lược Gia', 'description' => 'Đánh bại Bot cấp độ Khó.', 'category' => 'pve', 'difficulty' => 'hard', 'reward_credits' => 600, 'reward_gems' => 15],
            ['code' => 'pve_beat_nightmare', 'title' => 'Kẻ Diệt AI', 'description' => 'Đánh bại Bot Cực Khó (AI Nguyên Tử).', 'category' => 'pve', 'difficulty' => 'nightmare', 'reward_credits' => 1200, 'reward_gems' => 30],
            ['code' => 'pve_use_3_skills', 'title' => 'Chuyên Gia Công Nghệ', 'description' => 'Kích hoạt 3 kỹ năng tác chiến khác nhau trong cùng 1 trận.', 'category' => 'pve', 'difficulty' => 'medium', 'reward_credits' => 300, 'reward_gems' => 10],
            ['code' => 'pve_recon_master', 'title' => 'Mắt Thần Vệ Tinh', 'description' => 'Dùng Radar 3x3 phát hiện từ 3 ô tàu địch trở lên.', 'category' => 'pve', 'difficulty' => 'medium', 'reward_credits' => 250, 'reward_gems' => 8],
            ['code' => 'pve_shield_master', 'title' => 'Lá Chắn Bất Khả Xâm Phạm', 'description' => 'Dùng Khiên Năng Lượng chặn đứng đòn bắn của Bot.', 'category' => 'pve', 'difficulty' => 'medium', 'reward_credits' => 400, 'reward_gems' => 12],
            ['code' => 'pve_flawless_fleet', 'title' => 'Hạm Đội Thép', 'description' => 'Thắng Bot Khó trở lên mà bảo toàn nguyên vẹn toàn bộ 5 tàu.', 'category' => 'pve', 'difficulty' => 'hard', 'reward_credits' => 800, 'reward_gems' => 25],
            ['code' => 'pve_no_items', 'title' => 'Chiến Binh Độc Hành', 'description' => 'Thắng Bot Khó trở lên mà không dùng bất kỳ kỹ năng nào.', 'category' => 'pve', 'difficulty' => 'hard', 'reward_credits' => 1000, 'reward_gems' => 35],
            ['code' => 'pve_sniper', 'title' => 'Xạ Thủ Đại Dương', 'description' => 'Đạt độ chính xác >= 60% khi thắng Bot Trung Bình trở lên.', 'category' => 'pve', 'difficulty' => 'medium', 'reward_credits' => 500, 'reward_gems' => 15],
            ['code' => 'pve_speedrun', 'title' => 'Tốc Chiến Tốc Thắng', 'description' => 'Hạ gục Bot Khó trong thời gian dưới 100 giây.', 'category' => 'pve', 'difficulty' => 'hard', 'reward_credits' => 750, 'reward_gems' => 20],
            ['code' => 'pve_streak_nightmare', 'title' => 'Huyền Thoại Bất Bại', 'description' => 'Hạ gục Bot Cực Khó với tỷ lệ sống sót của hạm đội >= 50%.', 'category' => 'pve', 'difficulty' => 'nightmare', 'reward_credits' => 2500, 'reward_gems' => 60],

            // === 15 THÀNH TỰU ĐẤU MẠNG (PVP) ===
            ['code' => 'pvp_first_match', 'title' => 'Thủy Thủ Mới Gia Nhập', 'description' => 'Tham gia trận hải chiến PvP trực tuyến đầu tiên.', 'category' => 'pvp', 'difficulty' => 'easy', 'reward_credits' => 100, 'reward_gems' => 5],
            ['code' => 'pvp_first_win', 'title' => 'Chiến Thắng Mở Màn', 'description' => 'Đánh bại người chơi khác trong trận đấu PvP đầu tiên.', 'category' => 'pvp', 'difficulty' => 'easy', 'reward_credits' => 250, 'reward_gems' => 8],
            ['code' => 'pvp_first_blood', 'title' => 'Đòn Đánh Phủ Đầu', 'description' => 'Bắn trúng tàu người chơi khác ngay phát pháo đầu tiên.', 'category' => 'pvp', 'difficulty' => 'easy', 'reward_credits' => 300, 'reward_gems' => 10],
            ['code' => 'pvp_recon_spot', 'title' => 'Vua Trinh Sát PvP', 'description' => 'Dùng Radar hoặc Sonar phát hiện vị trí tàu của đối thủ online.', 'category' => 'pvp', 'difficulty' => 'medium', 'reward_credits' => 350, 'reward_gems' => 12],
            ['code' => 'pvp_clutch_win', 'title' => 'Đảo Ngược Tình Thế', 'description' => 'Chỉ còn 1 tàu sống sót nhưng vẫn lội ngược dòng thắng trận PvP.', 'category' => 'pvp', 'difficulty' => 'hard', 'reward_credits' => 800, 'reward_gems' => 25],
            ['code' => 'pvp_relocate_juke', 'title' => 'Bậc Thầy Nghi Binh', 'description' => 'Cơ động tàu sang vị trí mới khiến địch bắn trượt liên tiếp.', 'category' => 'pvp', 'difficulty' => 'medium', 'reward_credits' => 600, 'reward_gems' => 20],
            ['code' => 'pvp_win_5', 'title' => 'Đô Đốc Tập Sự', 'description' => 'Tích lũy đạt 5 trận thắng PvP.', 'category' => 'pvp', 'difficulty' => 'medium', 'reward_credits' => 700, 'reward_gems' => 20],
            ['code' => 'pvp_win_15', 'title' => 'Đô Đốc Dạn Dày', 'description' => 'Tích lũy đạt 15 trận thắng PvP.', 'category' => 'pvp', 'difficulty' => 'hard', 'reward_credits' => 1500, 'reward_gems' => 45],
            ['code' => 'pvp_win_30', 'title' => 'Chỉ Huy Tối Cao', 'description' => 'Tích lũy đạt 30 trận thắng PvP.', 'category' => 'pvp', 'difficulty' => 'nightmare', 'reward_credits' => 3000, 'reward_gems' => 80],
            ['code' => 'pvp_streak_3', 'title' => 'Không Thể Cản Phá', 'description' => 'Đạt chuỗi thắng 3 trận liên tiếp trong đấu trường PvP.', 'category' => 'pvp', 'difficulty' => 'medium', 'reward_credits' => 900, 'reward_gems' => 30],
            ['code' => 'pvp_streak_7', 'title' => 'Chuỗi Bất Bại', 'description' => 'Đạt chuỗi thắng 7 trận liên tiếp trong đấu trường PvP.', 'category' => 'pvp', 'difficulty' => 'nightmare', 'reward_credits' => 2000, 'reward_gems' => 60],
            ['code' => 'pvp_speed_destroyer', 'title' => 'Hỏa Lực Thần Tốc', 'description' => 'Hạ gục đối thủ online chỉ trong vòng dưới 25 lượt bắn.', 'category' => 'pvp', 'difficulty' => 'hard', 'reward_credits' => 1200, 'reward_gems' => 35],
            ['code' => 'pvp_accuracy_king', 'title' => 'Bách Phát Bách Trúng', 'description' => 'Đạt độ chính xác >= 70% trong một trận đấu đối kháng PvP.', 'category' => 'pvp', 'difficulty' => 'hard', 'reward_credits' => 1000, 'reward_gems' => 30],
            ['code' => 'pvp_flawless_victory', 'title' => 'Bảo Toàn Tuyệt Đối', 'description' => 'Thắng trận PvP mà không để mất bất kỳ chiếc tàu nào.', 'category' => 'pvp', 'difficulty' => 'hard', 'reward_credits' => 1500, 'reward_gems' => 50],
            ['code' => 'pvp_legendary_champion', 'title' => 'Hải Vương Tối Thượng', 'description' => 'Đánh bại 1 đối thủ thuộc Top 5 Bảng Xếp Hạng PvP.', 'category' => 'pvp', 'difficulty' => 'nightmare', 'reward_credits' => 5000, 'reward_gems' => 100],
        ];

        foreach ($achievements as $item) {
            DB::table('achievements')->updateOrInsert(
                ['code' => $item['code']],
                array_merge($item, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}