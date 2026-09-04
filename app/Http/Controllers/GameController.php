<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AchievementController;
use App\Models\Game;
use App\Models\Leaderboard;
use Carbon\Carbon;
use App\Services\BotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameController extends Controller
{
    protected BotService $botService;

    public function __construct(BotService $botService)
    {
        $this->botService = $botService;
    }

    protected function formatCoord(int $x, int $y): string
    {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $letter = $letters[$y] ?? '?';
        $num = $x + 1;
        return "{$letter}{$num}";
    }

    protected function calculateEarnedGems(Game $game, array $stats): int
    {
        $gems = 0;

        $diffGems = ['easy' => 2, 'medium' => 3, 'hard' => 5, 'nightmare' => 8];
        $gems += ($diffGems[$game->difficulty] ?? 2);

        $acc = $stats['accuracy'];
        if ($acc >= 75) $gems += 4;
        elseif ($acc >= 60) $gems += 3;
        elseif ($acc >= 50) $gems += 2;
        elseif ($acc >= 35) $gems += 1;

        $fleetHp = $stats['fleet_health'];
        if ($fleetHp >= 80) $gems += 4;
        elseif ($fleetHp >= 65) $gems += 3;
        elseif ($fleetHp >= 55) $gems += 2;
        elseif ($fleetHp >= 30) $gems += 1;

        $score = $stats['score'];
        $gems += (int) floor($score / 2500);

        return $gems;
    }

    public function create(Request $request): JsonResponse
    {
        $difficulty = $request->input('difficulty', 'easy');
        $mode = $request->input('mode', 'pve');
        $customPlayerShips = $request->input('player_ships');

        $playerShips = !empty($customPlayerShips) 
            ? $customPlayerShips 
            : $this->botService->placeFleetRandomly();

        $botShips = $this->botService->placeFleetRandomly();
        $botLoadout = $this->generateBotLoadout($difficulty);

        $game = Game::create([
            'session_id'   => Str::uuid()->toString(),
            'mode'         => $mode,
            'difficulty'   => $difficulty,
            'status'       => 'playing',
            'current_turn' => 'player',
            'player_ships' => $playerShips,
            'player_shots' => [],
            'bot_ships'    => $botShips,
            'bot_shots'    => [],
            'bot_memory'   => [
                'targets'               => [],
                'bot_loadout'           => $botLoadout,
                'player_used_powers'    => [], 
                'player_used_categories'=> [],
                'bot_shield_charges'    => 0,
                'bot_shield_turns'      => 0,
                'bot_smoke_turns'       => 0,
                'smoke_hidden_cells'    => [],
                'player_shield_charges' => 0,
                'player_shield_turns'   => 0,
                'player_smoke_turns'    => 0,
                'skip_next_turn'        => false,
            ],
            'started_at'   => now(),
        ]);

        return response()->json([
            'game_id'          => $game->id,
            'status'           => $game->status,
            'difficulty'       => $game->difficulty,
            'player_ships'     => $game->player_ships,
            'bot_items_count'  => count($botLoadout),
        ]);
    }

    protected function generateBotLoadout(string $difficulty): array
    {
        $categories = [
            'recon' => [
                'weak'   => 'recon_sonar',
                'medium' => 'recon_scan',
                'strong' => 'recon_sat',
            ],
            'combat' => [
                'weak'   => 'combat_smokescreen',
                'medium' => 'combat_airstrike',
                'strong' => 'combat_guided',
            ],
            'defend' => [
                'weak'   => 'def_tactical_relocate',
                'medium' => 'def_repair_relocate',
                'strong' => 'def_shield',
            ],
        ];

        $loadout = [];

        foreach ($categories as $cat => $tiers) {
            $chosenItem = null;

            switch ($difficulty) {
                case 'easy':
                    if (rand(1, 100) <= 50) {
                        $chosenItem = (rand(1, 100) <= 60) ? $tiers['weak'] : $tiers['medium'];
                    }
                    break;
                case 'medium':
                    $roll = rand(1, 100);
                    if ($roll <= 40) $chosenItem = $tiers['weak'];
                    elseif ($roll <= 80) $chosenItem = $tiers['medium'];
                    else $chosenItem = $tiers['strong'];
                    break;
                case 'hard':
                    $chosenItem = (rand(1, 100) <= 50) ? $tiers['medium'] : $tiers['strong'];
                    break;
                case 'nightmare':
                    $chosenItem = (rand(1, 100) <= 30) ? $tiers['medium'] : $tiers['strong'];
                    break;
            }

            if ($chosenItem) {
                $maxUses = 1;
                $isMedium = in_array($chosenItem, ['recon_scan', 'combat_airstrike', 'def_repair_relocate']);
                if ($difficulty === 'nightmare' && $isMedium) {
                    $maxUses = 2;
                }
                $loadout[$chosenItem] = $maxUses;
            }
        }

        return $loadout;
    }

    public function fire(Request $request, Game $game): JsonResponse
    {
        if ($game->status !== 'playing' || $game->current_turn !== 'player') {
            return response()->json(['error' => 'Chưa đến lượt hoặc game đã kết thúc.'], 400);
        }

        $isTimeout = (bool) $request->input('timeout', false);

        if ($isTimeout) {
            $game->current_turn = 'bot';
            $botTurnResult = $this->executeBotTurn($game);
            $game->save();

            return response()->json([
                'player_shot'  => null,
                'bot_shot'     => $botTurnResult,
                'game_status'  => $game->status,
                'current_turn' => $game->current_turn,
            ]);
        }

        $x = (int) $request->input('x');
        $y = (int) $request->input('y');

        $botShots = $game->bot_shots ?? [];
        foreach ($botShots as $shot) {
            if ($shot['x'] === $x && $shot['y'] === $y) {
                return response()->json(['error' => 'Tọa độ này đã được bắn trước đó!'], 422);
            }
        }

        $mem = $game->bot_memory ?? [];
        $hitResult = $this->processShot($game->bot_ships, $x, $y);

        if (($mem['bot_shield_turns'] ?? 0) > 0) {
            $mem['bot_shield_turns']--;
            if ($mem['bot_shield_turns'] <= 0) {
                $mem['bot_shield_charges'] = 0;
            }
        }

        if (($mem['bot_shield_charges'] ?? 0) > 0 && in_array($hitResult['result'], ['hit', 'sunk'])) {
            $mem['bot_shield_charges']--;
            $hitResult['result'] = 'shield_blocked';
        }

        $realResult = $hitResult['result'];
        $displayedResult = $realResult;
        $revealedSmokeCells = [];

        if (($mem['bot_smoke_turns'] ?? 0) > 0) {
            $mem['bot_smoke_turns']--;
            $displayedResult = 'smoke_hidden';

            $mem['smoke_hidden_cells'][] = [
                'x'      => $x,
                'y'      => $y,
                'result' => $realResult,
            ];

            if ($mem['bot_smoke_turns'] <= 0) {
                $revealedSmokeCells = $mem['smoke_hidden_cells'] ?? [];
                $mem['smoke_hidden_cells'] = [];
            }
        }

        $botShots[] = [
            'x'      => $x,
            'y'      => $y,
            'result' => $realResult,
            'ship'   => $hitResult['ship_name'] ?? null,
        ];

        $game->bot_ships = $hitResult['ships'];
        $game->bot_shots = $botShots;
        $game->bot_memory = $mem;

        // KIỂM TRA MỞ KHÓA THÀNH TỰU KHI BẮN
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $unlockedAchievements = [];

        if ($user) {
            if (AchievementController::unlock($user, 'pve_first_shot')) {
                $unlockedAchievements[] = 'Phát Đạn Đầu Tiên';
            }
            if (in_array($realResult, ['hit', 'sunk'])) {
                if (AchievementController::unlock($user, 'pve_first_hit')) {
                    $unlockedAchievements[] = 'Hỏa Lực Khai Mào';
                }
            }
            if ($realResult === 'sunk') {
                if (AchievementController::unlock($user, 'pve_first_sunk')) {
                    $unlockedAchievements[] = 'Mồi Cho Cá Biển';
                }
            }
        }

        if ($this->isFleetDestroyed($game->bot_ships)) {
            $game->status = 'won';

            if (!empty($mem['smoke_hidden_cells'])) {
                $revealedSmokeCells = array_merge($revealedSmokeCells, $mem['smoke_hidden_cells']);
                $mem['smoke_hidden_cells'] = [];
                $game->bot_memory = $mem;
            }

            $game->save();

            $stats = $this->calculateScore($game);
            $earnedCredits = (int) floor($stats['score'] / 100);
            $earnedGems = $this->calculateEarnedGems($game, $stats);

            if ($user) {
                $userStats = $user->stats ?? [];
                $diff = $game->difficulty;
                $userStats[$diff]['wins'] = ($userStats[$diff]['wins'] ?? 0) + 1;
                if ($stats['score'] > ($userStats[$diff]['high_score'] ?? 0)) {
                    $userStats[$diff]['high_score'] = $stats['score'];
                }
                $user->stats = $userStats;
                $user->credits = ($user->credits ?? 0) + $earnedCredits;
                $user->gems = ($user->gems ?? 0) + $earnedGems;
                $user->save();

                // Kiểm tra thành tựu khi thắng ván
                $beatMap = [
                    'easy'      => 'pve_beat_easy',
                    'medium'    => 'pve_beat_medium',
                    'hard'      => 'pve_beat_hard',
                    'nightmare' => 'pve_beat_nightmare',
                ];
                if (isset($beatMap[$diff]) && AchievementController::unlock($user, $beatMap[$diff])) {
                    $unlockedAchievements[] = 'Đánh Bại Bot Cấp ' . strtoupper($diff);
                }

                if (in_array($diff, ['hard', 'nightmare']) && $stats['fleet_health'] >= 100) {
                    if (AchievementController::unlock($user, 'pve_flawless_fleet')) {
                        $unlockedAchievements[] = 'Hạm Đội Thép';
                    }
                }

                $playerUsedPowers = $mem['player_used_powers'] ?? [];
                if (in_array($diff, ['hard', 'nightmare']) && empty($playerUsedPowers)) {
                    if (AchievementController::unlock($user, 'pve_no_items')) {
                        $unlockedAchievements[] = 'Chiến Binh Độc Hành';
                    }
                }

                if (in_array($diff, ['medium', 'hard', 'nightmare']) && $stats['accuracy'] >= 60) {
                    if (AchievementController::unlock($user, 'pve_sniper')) {
                        $unlockedAchievements[] = 'Xạ Thủ Đại Dương';
                    }
                }

                if (in_array($diff, ['hard', 'nightmare']) && $stats['duration_seconds'] <= 100) {
                    if (AchievementController::unlock($user, 'pve_speedrun')) {
                        $unlockedAchievements[] = 'Tốc Chiến Tốc Thắng';
                    }
                }

                if ($diff === 'nightmare' && $stats['fleet_health'] >= 50) {
                    if (AchievementController::unlock($user, 'pve_streak_nightmare')) {
                        $unlockedAchievements[] = 'Huyền Thoại Bất Bại';
                    }
                }
            }

            $playerShotResponse = end($botShots);
            $playerShotResponse['display_result'] = $displayedResult;

            return response()->json([
                'player_shot'           => $playerShotResponse,
                'revealed_smoke_cells'  => $revealedSmokeCells, 
                'game_status'           => 'won',
                'stats'                 => $stats,
                'earned_credits'        => $earnedCredits,
                'earned_gems'           => $earnedGems,
                'total_credits'         => $user ? $user->credits : 0,
                'total_gems'            => $user ? $user->gems : 0,
                'user_stats'            => $user ? $user->stats : null,
                'unlocked_achievements' => $unlockedAchievements,
                'message'               => 'Chúc mừng! Bạn đã bắn chìm toàn bộ hạm đội đối phương!',
            ]);
        }

        $botTurnResult = null;
        $botExtraTurnResult = null;
        $botUsedPower = null;

        if ($hitResult['result'] === 'miss' || $hitResult['result'] === 'shield_blocked') {
            $game->current_turn = 'bot';

            if (!empty($mem['skip_next_turn'])) {
                $mem['skip_next_turn'] = false;
                $game->bot_memory = $mem;
                $game->current_turn = 'player';
                $game->save();

                $playerShotResponse = end($botShots);
                $playerShotResponse['display_result'] = $displayedResult;

                return response()->json([
                    'player_shot'           => $playerShotResponse,
                    'revealed_smoke_cells'  => $revealedSmokeCells,
                    'bot_shot'              => null,
                    'bot_skipped'           => true,
                    'unlocked_achievements' => $unlockedAchievements,
                    'game_status'           => $game->status,
                    'current_turn'          => 'player',
                ]);
            }

            $botUsedPower = $this->tryBotTacticalSkill($game);
            $botTurnResult = $this->executeBotTurn($game);

            if ($botUsedPower && $botUsedPower['item'] === 'combat_airstrike' && $game->status === 'playing') {
                $botExtraTurnResult = $this->executeBotTurn($game);
            }
        }

        $game->save();

        $playerShotResponse = end($botShots);
        $playerShotResponse['display_result'] = $displayedResult;

        return response()->json([
            'player_shot'           => $playerShotResponse,
            'revealed_smoke_cells'  => $revealedSmokeCells,
            'bot_shot'              => $botTurnResult,
            'bot_extra_shot'        => $botExtraTurnResult,
            'bot_used_power'        => $botUsedPower,
            'unlocked_achievements' => $unlockedAchievements,
            'game_status'           => $game->status,
            'current_turn'          => $game->current_turn,
        ]);
    }

    protected function tryBotTacticalSkill(Game $game): ?array
    {
        $mem = $game->bot_memory ?? [];
        $loadout = $mem['bot_loadout'] ?? [];

        $availableItems = [];
        foreach ($loadout as $item => $remainingUses) {
            if ($remainingUses > 0) {
                $availableItems[] = $item;
            }
        }

        if (empty($availableItems)) return null;

        $chances = ['easy' => 15, 'medium' => 25, 'hard' => 45, 'nightmare' => 65];
        if (rand(1, 100) > ($chances[$game->difficulty] ?? 20)) return null;

        $botDamaged = false;
        foreach ($game->bot_ships as $bs) {
            if ($bs['hits'] > 0 && $bs['hits'] < $bs['size']) {
                $botDamaged = true;
                break;
            }
        }

        $selectedItem = null;
        if ($botDamaged) {
            if (in_array('def_shield', $availableItems)) $selectedItem = 'def_shield';
            elseif (in_array('def_repair_relocate', $availableItems)) $selectedItem = 'def_repair_relocate';
        }

        if (!$selectedItem && empty($mem['targets'])) {
            if (in_array('recon_scan', $availableItems)) $selectedItem = 'recon_scan';
            elseif (in_array('recon_sonar', $availableItems)) $selectedItem = 'recon_sonar';
            elseif (in_array('combat_guided', $availableItems)) $selectedItem = 'combat_guided';
            elseif (in_array('recon_sat', $availableItems)) $selectedItem = 'recon_sat';
        }

        if (!$selectedItem) {
            $selectedItem = $availableItems[array_rand($availableItems)];
        }

        $loadout[$selectedItem]--;
        $mem['bot_loadout'] = $loadout;

        $publicAnnouncement = '';
        $effectData = ['item' => $selectedItem];

        switch ($selectedItem) {
            case 'recon_scan':
                $firedKeys = [];
                foreach ($game->player_shots ?? [] as $ps) $firedKeys["{$ps['x']},{$ps['y']}"] = true;

                $scanCenterX = rand(1, 8);
                $scanCenterY = rand(1, 8);
                $enemyCoords = [];
                foreach ($game->player_ships as $s) {
                    foreach ($s['coordinates'] as $c) $enemyCoords["{$c['x']},{$c['y']}"] = true;
                }

                for ($dx = -1; $dx <= 1; $dx++) {
                    for ($dy = -1; $dy <= 1; $dy++) {
                        $tx = $scanCenterX + $dx; $ty = $scanCenterY + $dy;
                        if (!isset($firedKeys["$tx,$ty"]) && isset($enemyCoords["$tx,$ty"])) {
                            $mem['targets'][] = ['x' => $tx, 'y' => $ty];
                        }
                    }
                }
                $publicAnnouncement = "Đối phương kích hoạt [RADAR QUÉT VÙNG 3X3] khoanh vùng hạm đội của bạn!";
                $effectData = [
                    'type' => 'bot_radar_scan',
                    'cx'   => $scanCenterX,
                    'cy'   => $scanCenterY,
                ];
                break;

            case 'recon_sonar':
                $scanCenterX = rand(2, 7);
                $scanCenterY = rand(2, 7);
                $unhitPlayerCoords = [];
                foreach ($game->player_ships as $ps) {
                    foreach ($ps['coordinates'] as $c) $unhitPlayerCoords[] = $c;
                }
                if (!empty($unhitPlayerCoords)) {
                    $mem['targets'][] = $unhitPlayerCoords[array_rand($unhitPlayerCoords)];
                }
                $publicAnnouncement = "Đối phương kích hoạt [SONAR CẢM BIẾN 5X5] phát xung sóng âm dò tìm vị trí!";
                $effectData = [
                    'type' => 'bot_sonar_pulse',
                    'cx'   => $scanCenterX,
                    'cy'   => $scanCenterY,
                ];
                break;

            case 'combat_guided':
                $unhitPlayerCoords = [];
                $shotKeys = [];
                foreach ($game->player_shots ?? [] as $s) $shotKeys["{$s['x']},{$s['y']}"] = true;
                foreach ($game->player_ships as $ps) {
                    if ($ps['hits'] < $ps['size']) {
                        foreach ($ps['coordinates'] as $c) {
                            if (!isset($shotKeys["{$c['x']},{$c['y']}"])) $unhitPlayerCoords[] = $c;
                        }
                    }
                }
                $lockedTarget = null;
                if (!empty($unhitPlayerCoords)) {
                    $lockedTarget = $unhitPlayerCoords[array_rand($unhitPlayerCoords)];
                    $mem['targets'][] = $lockedTarget;
                }
                $publicAnnouncement = "CẢNH BÁO: Đối phương đã khai hỏa [TÊN LỬA DẪN ĐƯỜNG] khóa mục tiêu!";
                $effectData = [
                    'type'   => 'bot_missile_lock',
                    'target' => $lockedTarget,
                ];
                break;

            case 'combat_smokescreen':
                $mem['bot_smoke_turns'] = 5;
                $publicAnnouncement = "Đối phương thả [MÀN KHÓI NHIỄU LOẠN]! Kết quả 5 phát bắn tiếp theo của bạn bị che giấu!";
                $effectData = [
                    'type' => 'bot_smoke_active',
                ];
                break;

            case 'combat_airstrike':
                $publicAnnouncement = "CẢNH BÁO: Đối phương phát động [KHÔNG KÍCH PHÁ RỐI]! Bạn bị MẤT 1 LƯỢT BẮN!";
                $effectData = [
                    'type' => 'bot_airstrike_shock',
                ];
                break;

            case 'def_shield':
                $mem['bot_shield_charges'] = 3;
                $mem['bot_shield_turns'] = 5;
                $publicAnnouncement = "Đối phương kích hoạt [KHIÊN NĂNG LƯỢNG] miễn nhiễm 3 phát đạn trúng!";
                $effectData = [
                    'type' => 'bot_shield_activated',
                ];
                break;

            case 'recon_sat':
                $unhitPlayerCoords = [];
                foreach ($game->player_ships as $ps) {
                    if ($ps['hits'] < $ps['size']) {
                        foreach ($ps['coordinates'] as $c) $unhitPlayerCoords[] = $c;
                    }
                }
                $scannedCoord = !empty($unhitPlayerCoords) ? $unhitPlayerCoords[array_rand($unhitPlayerCoords)] : null;
                $publicAnnouncement = "Đối phương đã triển khai [VỆ TINH QUÂN SỰ] quét trinh sát quỹ đạo hạm đội ta!";
                $effectData = [
                    'type'  => 'bot_satellite_sweep',
                    'coord' => $scannedCoord,
                ];
                break;

            case 'def_repair_relocate':
                $publicAnnouncement = "Đối phương đã kích hoạt [TÁI CẤU TRÚC] phục hồi hư hại và đổi vị trí tàu!";
                $effectData = [
                    'type' => 'bot_ship_relocated',
                ];
                break;

            case 'def_tactical_relocate':
                $publicAnnouncement = "Đối phương đã kích hoạt [CƠ ĐỘNG CHIẾN THUẬT] bí mật đổi hướng hành quân!";
                $effectData = [
                    'type' => 'bot_ship_relocated',
                ];
                break;

            default:
                $publicAnnouncement = "Đối phương đã kích hoạt một trang bị tác chiến bí mật!";
                break;
        }

        $game->bot_memory = $mem;

        return [
            'item'   => $selectedItem,
            'msg'    => $publicAnnouncement,
            'effect' => $effectData,
        ];
    }

    public function executeBotTurn(Game $game): array
    {
        $botMove = $this->botService->makeMove(
            $game->difficulty,
            $game->player_shots ?? [],
            $game->bot_memory,
            $game->player_ships
        );

        $x = $botMove['x'];
        $y = $botMove['y'];
        $memory = $botMove['memory'] ?? ['targets' => []];

        $mem = $game->bot_memory ?? [];
        $hitResult = $this->processShot($game->player_ships, $x, $y);

        if (($mem['player_shield_charges'] ?? 0) > 0 && in_array($hitResult['result'], ['hit', 'sunk'])) {
            $mem['player_shield_charges']--;
            $hitResult['result'] = 'player_shield_blocked';
        }

        $playerShots = $game->player_shots ?? [];
        $playerShots[] = [
            'x'      => $x,
            'y'      => $y,
            'result' => $hitResult['result'],
            'ship'   => $hitResult['ship_name'] ?? null,
        ];

        if ($hitResult['result'] === 'hit') {
            $adjacent = [
                ['x' => $x + 1, 'y' => $y],
                ['x' => $x - 1, 'y' => $y],
                ['x' => $x, 'y' => $y + 1],
                ['x' => $x, 'y' => $y - 1],
            ];

            foreach ($adjacent as $adj) {
                if ($adj['x'] >= 0 && $adj['x'] < 10 && $adj['y'] >= 0 && $adj['y'] < 10) {
                    $memory['targets'][] = $adj;
                }
            }
        }

        $game->player_ships = $hitResult['ships'];
        $game->player_shots = $playerShots;
        $game->bot_memory = array_merge($mem, $memory);

        if ($this->isFleetDestroyed($game->player_ships)) {
            $game->status = 'lost';
        } else {
            $game->current_turn = 'player';
        }

        return end($playerShots);
    }

    protected function processShot(array $ships, int $x, int $y): array
    {
        $result = 'miss';
        $hitShipName = null;

        foreach ($ships as &$ship) {
            foreach ($ship['coordinates'] as $coord) {
                if ($coord['x'] === $x && $coord['y'] === $y) {
                    $result = 'hit';
                    $ship['hits']++;
                    $hitShipName = $ship['name'];

                    if ($ship['hits'] >= $ship['size']) {
                        $result = 'sunk';
                    }
                    break 2;
                }
            }
        }

        return [
            'result'    => $result,
            'ship_name' => $hitShipName,
            'ships'     => $ships,
        ];
    }

    protected function isFleetDestroyed(array $ships): bool
    {
        foreach ($ships as $ship) {
            if ($ship['hits'] < $ship['size']) {
                return false;
            }
        }
        return true;
    }

    protected function calculateScore(Game $game): array
    {
        $startTime = $game->started_at ? Carbon::parse($game->started_at) : Carbon::parse($game->created_at);
        $duration = max(1, (int) $startTime->diffInSeconds(now()));
        
        $botShots = $game->bot_shots ?? [];
        $totalFired = count($botShots);
        $hitCount = count(array_filter($botShots, fn($s) => in_array($s['result'], ['hit', 'sunk'])));
        $accuracy = $totalFired > 0 ? round(($hitCount / $totalFired) * 100, 1) : 0;

        $shipValues = [
            'Carrier' => 1000,
            'Battleship' => 800,
            'Cruiser' => 500,
            'Submarine' => 500,
            'Destroyer' => 300,
        ];
        
        $fleetScore = 0;
        $totalHealthPoints = 0;
        $remainingHealthPoints = 0;

        foreach ($game->player_ships as $ship) {
            $val = $shipValues[$ship['name']] ?? 400;
            $dmgRatio = $ship['hits'] / $ship['size'];
            $survivedRatio = max(0, 1 - $dmgRatio);
            
            $fleetScore += round($val * $survivedRatio);
            $totalHealthPoints += $ship['size'];
            $remainingHealthPoints += ($ship['size'] - $ship['hits']);
        }

        $fleetHealthPercent = round(($remainingHealthPoints / $totalHealthPoints) * 100);
        $timeBonus = max(0, (300 - $duration)) * 10;
        $accuracyBonus = round(($accuracy / 100) * 2000);

        $multipliers = [
            'easy' => 1.0,
            'medium' => 1.4,
            'hard' => 1.8,
            'nightmare' => 2.5,
        ];
        $mult = $multipliers[$game->difficulty] ?? 1.0;

        $rawScore = 5000 + $timeBonus + $accuracyBonus + $fleetScore;
        $finalScore = (int) round($rawScore * $mult);

        return [
            'score'             => $finalScore,
            'duration_seconds'  => $duration,
            'accuracy'          => $accuracy,
            'fleet_health'      => $fleetHealthPercent,
            'difficulty'        => $game->difficulty,
        ];
    }

    public function getLeaderboard(Request $request): JsonResponse
    {
        $difficulty = $request->input('difficulty', 'medium');

        $topPlayers = Leaderboard::where('difficulty', $difficulty)
            ->orderByDesc('score')
            ->orderBy('duration_seconds')
            ->take(5)
            ->get(['player_name', 'score', 'duration_seconds', 'accuracy', 'fleet_health']);

        return response()->json($topPlayers);
    }

    public function saveScore(Request $request, Game $game): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        $rawName = $user ? $user->name : $request->input('player_name', 'Chỉ Huy Ẩn Danh');
        $name = trim($rawName) ?: 'Chỉ Huy Ẩn Danh';
        $stats = $this->calculateScore($game);

        $record = Leaderboard::create([
            'player_name'      => substr($name, 0, 20),
            'difficulty'       => $game->difficulty,
            'score'            => $stats['score'],
            'duration_seconds' => $stats['duration_seconds'],
            'accuracy'         => $stats['accuracy'],
            'fleet_health'     => $stats['fleet_health'],
        ]);

        $earnedCredits = 0;
        if ($user) {
            $earnedCredits = (int) floor($stats['score'] / 100);
            $user->credits = ($user->credits ?? 0) + $earnedCredits;
            $user->save();
        }

        return response()->json([
            'status'         => 'success',
            'record'         => $record,
            'earned_credits' => $earnedCredits,
            'total_credits'  => $user ? $user->credits : 0,
            'user_stats'     => $user ? $user->stats : null,
        ]);
    }
}