<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PvpRankedBotsSeeder extends Seeder
{
    public function run(): void
    {
        // Nếu đã có bot trong DB rồi thì bỏ qua, không xóa đi tạo lại để tránh xáo trộn dữ liệu
        if (User::where('is_bot', true)->count() >= 50) {
            return;
        }

        // Xóa bot cũ nếu có trước khi tạo mới (chỉ khi số lượng chưa đủ)
        User::where('is_bot', true)->delete();

        $namesPool = [
            'HaiTac_PhanThiet', 'ThuyThu_MienTay', 'TuanDoi_TruongSa', 'TuanDuong_HanhQuan',
            'SubHunter_VN', 'KiemNgu_BienDong', 'HaiAu_TrieuDo', 'GhostFleet_Zero',
            'PhanPhao_HapDan', 'ThietGiap_Ham', 'HaiThan_Poseidon', 'ThuyLoi_Dem',
            'KhuTruc_Vanguard', 'SongNgam_BlackSea', 'TiemKich_HaiQuan', 'NguLoi_GiaCam',
            'RedAlert_Commander', 'HaiQuan_ChienLuoc', 'BaoBien_HaiTac', 'NhanKhu_AmSat',
            'PhuongHoang_Bien', 'RongBien_DeepBlue', 'BongMa_HaiVuc', 'DiaLoi_VoHinh',
            'HaiTrieu_CuongNo', 'ChienHam_Yamato', 'PhanLuc_SuperHornet', 'LoiDinh_PhanKich',
            'Submariner_99', 'HaiLuc_BaoTap', 'BachTuoc_KhongLo', 'HaiSu_CanVe',
            'CungThu_BienBac', 'NghiBinh_BaoCat', 'PhucKich_DemKhuyet', 'ThuyQuan_LucChien',
            'TuanTra_HoangSa', 'ChienBinh_VinhBacBo', 'LietHoa_HaiChien', 'ChiHuy_ToiCao_VIP',
            'Ironclad_Alpha', 'StormBringer_VN', 'NavalStriker', 'AbyssalHunter',
            'Triton_Master', 'OceanPredator', 'Kraken_Ruler', 'Leviathan_VN',
            'SeaWolf_Tactics', 'Corsair_VN', 'DeepDiver_77', 'Torpedo_Ace',
            'SonarMaster_Pro', 'Vanguard_Leader', 'Dreadnought_X', 'Warspite_VN',
            'Bismarck_Hunter', 'Enterprise_Ace', 'Yamato_Spirit', 'Missouri_Gunner',
            'Nautilus_Echo', 'ShadowFleet_99', 'Poseidon_Wrath', 'AquaStrike_VN',
            'Neptune_Spear', 'Pacific_Ghost', 'Atlantic_Fox', 'Arctic_Wolf',
            'Baltic_Corsair', 'Coral_Guardian', 'Typhoon_Chaser', 'Tsunami_Rider',
            'Manta_Ray_VN', 'Hammerhead_Strike', 'Barracuda_Speed', 'Megalodon_Bite',
            'Orca_Tactics', 'SeaViper_Sniper', 'Moray_Ambush', 'Stingray_Silent',
            'ViperFish_Deep', 'Angler_Lure', 'Nautilus_Prime', 'Kraken_Awakened',
            'Leviathan_Rage', 'Poseidon_Shield', 'Triton_Trident', 'Neptune_Storm',
            'Aegean_Corsair', 'Ionian_Raider', 'Caspian_Phantom', 'Adriatic_Ghost',
            'NorthSea_Baron', 'SouthSea_Dragon', 'EastSea_Hawk', 'WestSea_Falcon',
            'Polaris_Naval', 'Orion_Fleet', 'Sirius_Admiral', 'Vega_Commander',
            'Altair_Cap', 'Rigel_Officer', 'Betelgeuse_Ace', 'Antares_Gunner',
            'Aldebaran_VN', 'Spica_Tactical', 'Deneb_Strike', 'Pollux_Cruiser',
            'Castor_Frigate', 'Regulus_Destroyer', 'Canopus_Carrier', 'Capella_Sub',
            'Arcturus_Heavy', 'Procyon_Light', 'Achernar_Fast', 'Hadar_Stealth',
        ];

        $ranksConfig = [
            'seaman' => [
                'count' => 25,
                'min_elo' => 200, 'max_elo' => 999,
                'diff_weights' => ['easy' => 70, 'medium' => 25, 'hard' => 4, 'nightmare' => 1],
                'acc_min' => 20, 'acc_max' => 38,
            ],
            'petty_officer' => [
                'count' => 30,
                'min_elo' => 1000, 'max_elo' => 1499,
                'diff_weights' => ['easy' => 35, 'medium' => 50, 'hard' => 12, 'nightmare' => 3],
                'acc_min' => 32, 'acc_max' => 46,
            ],
            'ensign' => [
                'count' => 25,
                'min_elo' => 1500, 'max_elo' => 1999,
                'diff_weights' => ['easy' => 15, 'medium' => 55, 'hard' => 25, 'nightmare' => 5],
                'acc_min' => 40, 'acc_max' => 54,
            ],
            'lieutenant' => [
                'count' => 20,
                'min_elo' => 2000, 'max_elo' => 2499,
                'diff_weights' => ['easy' => 5, 'medium' => 35, 'hard' => 45, 'nightmare' => 15],
                'acc_min' => 48, 'acc_max' => 62,
            ],
            'captain' => [
                'count' => 15,
                'min_elo' => 2500, 'max_elo' => 2999,
                'diff_weights' => ['easy' => 0, 'medium' => 15, 'hard' => 55, 'nightmare' => 30],
                'acc_min' => 56, 'acc_max' => 70,
            ],
            'fleet_admiral' => [
                'count' => 8,
                'min_elo' => 3000, 'max_elo' => 3600,
                'diff_weights' => ['easy' => 0, 'medium' => 5, 'hard' => 45, 'nightmare' => 50],
                'acc_min' => 65, 'acc_max' => 82,
            ],
        ];

        shuffle($namesPool);
        $nameIndex = 0;

        foreach ($ranksConfig as $tier => $cfg) {
            for ($i = 0; $i < $cfg['count']; $i++) {
                $botName = $namesPool[$nameIndex++] ?? ('Commander_Bot_' . rand(100, 9999));

                $randRoll = rand(1, 100);
                $cum = 0;
                $botDiff = 'medium';
                foreach ($cfg['diff_weights'] as $diff => $weight) {
                    $cum += $weight;
                    if ($randRoll <= $cum) {
                        $botDiff = $diff;
                        break;
                    }
                }

                $elo = rand($cfg['min_elo'], $cfg['max_elo']);
                $accuracy = round(rand($cfg['acc_min'] * 10, $cfg['acc_max'] * 10) / 10, 1);
                
                if (in_array($tier, ['seaman', 'petty_officer']) && in_array($botDiff, ['hard', 'nightmare'])) {
                    $accuracy = round(rand(680, 850) / 10, 1);
                }

                $totalMatches = rand(20, 180);
                $winRate = ($elo / 4000) * 0.45 + 0.35;
                $wins = (int) round($totalMatches * $winRate);
                $losses = max(1, $totalMatches - $wins);

                User::create([
                    'name'            => $botName,
                    'email'           => Str::slug($botName) . '@naval.bot.sim',
                    'password'        => Hash::make('bot_secret_naval_pass_2026'),
                    'credits'         => rand(200, 8000),
                    'gems'            => rand(10, 250),
                    'inventory'       => [],
                    'daily_purchases' => [],
                    'stats'           => [],
                    'elo'             => $elo,
                    'rank_tier'       => $tier,
                    'pvp_wins'        => $wins,
                    'pvp_losses'      => $losses,
                    'accuracy_rate'   => $accuracy,
                    'is_bot'          => true,
                    'bot_difficulty'  => $botDiff,
                ]);
            }
        }
    }
}