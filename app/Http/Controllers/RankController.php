<?php

namespace App\Http\Controllers;

use App\Events\PvpGameStarted;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RankController extends Controller
{
    public const RANK_TIERS = [
        'seaman' => [
            'name'      => 'Thủy Thủ (Seaman)',
            'short'     => 'Thủy Thủ',
            'badge'     => '⚓',
            'min_elo'   => 0,
            'max_elo'   => 999,
        ],
        'petty_officer' => [
            'name'      => 'Hạ Sĩ Quan (Petty Officer)',
            'short'     => 'Hạ Sĩ Quan',
            'badge'     => '🎖️',
            'min_elo'   => 1000,
            'max_elo'   => 1499,
        ],
        'ensign' => [
            'name'      => 'Sĩ Quan Sơ Cấp (Ensign)',
            'short'     => 'Sĩ Quan',
            'badge'     => '⭐',
            'min_elo'   => 1500,
            'max_elo'   => 1999,
        ],
        'lieutenant' => [
            'name'      => 'Thiếu Tá Hạm Đội (Lieutenant)',
            'short'     => 'Thiếu Tá',
            'badge'     => '⭐⭐',
            'min_elo'   => 2000,
            'max_elo'   => 2499,
        ],
        'captain' => [
            'name'      => 'Đại Tá Hải Quân (Captain)',
            'short'     => 'Đại Tá',
            'badge'     => '⭐⭐⭐',
            'min_elo'   => 2500,
            'max_elo'   => 2999,
        ],
        'fleet_admiral' => [
            'name'      => 'Đô Đốc Tối Cao (Fleet Admiral)',
            'short'     => 'Đô Đốc',
            'badge'     => '👑',
            'min_elo'   => 3000,
            'max_elo'   => 9999,
        ],
    ];

    public function getLadder(Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $tier = $request->input('tier', 'seaman');

        if (!array_key_exists($tier, self::RANK_TIERS)) {
            $tier = 'seaman';
        }

        $this->simulateBackgroundBotMatches();

        if ($currentUser) {
            $currentUserTier = $this->calculateTier($currentUser->elo ?? 500);
            if ($currentUser->rank_tier !== $currentUserTier) {
                $currentUser->rank_tier = $currentUserTier;
                $currentUser->save();
            }
        }

        $players = User::where('rank_tier', $tier)
            ->orderByDesc('elo')
            ->orderByDesc('pvp_wins')
            ->get();

        $tierConfig = self::RANK_TIERS[$tier];
        $ladderData = [];
        $rankIndex = 1;

        foreach ($players as $p) {
            $isMe = $currentUser && ($p->id === $currentUser->id);
            $total = ($p->pvp_wins ?? 0) + ($p->pvp_losses ?? 0);
            $winRate = $total > 0 ? round(($p->pvp_wins / $total) * 100, 1) : 0;

            $ladderData[] = [
                'rank'           => $rankIndex++,
                'id'             => $p->id,
                'name'           => $p->name,
                'elo'            => $p->elo ?? 500,
                'tier_key'       => $tier,
                'tier_name'      => $tierConfig['short'],
                'badge'          => $tierConfig['badge'],
                'wins'           => $p->pvp_wins ?? 0,
                'losses'         => $p->pvp_losses ?? 0,
                'win_rate'       => $winRate,
                'accuracy'       => $p->accuracy_rate ?? 30.0,
                'is_bot'         => (bool) $p->is_bot,
                'is_smurf'       => (bool) ($p->is_bot && in_array($p->bot_difficulty, ['hard', 'nightmare']) && in_array($tier, ['seaman', 'petty_officer'])),
                'is_me'          => $isMe,
            ];
        }

        $tierCounts = [];
        foreach (array_keys(self::RANK_TIERS) as $tKey) {
            $tierCounts[$tKey] = User::where('rank_tier', $tKey)->count();
        }

        $myStats = null;
        if ($currentUser) {
            $myTier = $currentUser->rank_tier ?? 'seaman';
            $myTierConfig = self::RANK_TIERS[$myTier] ?? self::RANK_TIERS['seaman'];
            $myTotal = ($currentUser->pvp_wins ?? 0) + ($currentUser->pvp_losses ?? 0);
            $myWinRate = $myTotal > 0 ? round(($currentUser->pvp_wins / $myTotal) * 100, 1) : 0;

            $myRankInMyTier = User::where('rank_tier', $myTier)
                ->where('elo', '>', $currentUser->elo ?? 500)
                ->count() + 1;

            $myStats = [
                'name'        => $currentUser->name,
                'elo'         => $currentUser->elo ?? 500,
                'tier_key'    => $myTier,
                'tier_name'   => $myTierConfig['name'],
                'tier_badge'  => $myTierConfig['badge'],
                'wins'        => $currentUser->pvp_wins ?? 0,
                'losses'      => $currentUser->pvp_losses ?? 0,
                'win_rate'    => $myWinRate,
                'accuracy'    => $currentUser->accuracy_rate ?? 35.0,
                'rank_number' => $myRankInMyTier,
            ];
        }

        return response()->json([
            'current_tier' => $tier,
            'tier_info'    => $tierConfig,
            'tier_counts'  => $tierCounts,
            'ladder'       => $ladderData,
            'my_stats'     => $myStats,
        ]);
    }

    /**
     * Hàng chờ ghép trận Rank: Ghép 2 người chơi thật ngay lập tức
     */
    public function matchmake(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $forceBot = (bool) $request->input('force_bot', false);
        $userTier = $this->calculateTier($user->elo ?? 500);

        // 1. Kiểm tra nếu user này đã được người chơi thật khác ghép vào phòng
        $myQueue = DB::table('rank_queues')->where('user_id', $user->id)->first();
        if ($myQueue && $myQueue->status === 'matched' && !empty($myQueue->room_code)) {
            $room = Room::where('room_code', $myQueue->room_code)->first();
            if ($room) {
                $opponentId = ($room->player1_id === $user->id) ? $room->player2_id : $room->player1_id;
                $opponentUser = User::find($opponentId);
                DB::table('rank_queues')->where('user_id', $user->id)->delete();

                return response()->json([
                    'status'     => 'matched_real',
                    'room'       => $room,
                    'room_code'  => $room->room_code,
                    'role'       => ($room->player1_id === $user->id) ? 'player1' : 'player2',
                    'opponent'   => [
                        'name'   => $opponentUser->name ?? 'Chỉ Huy Trực Tuyến',
                        'is_bot' => false,
                        'elo'    => $opponentUser->elo ?? 500,
                    ],
                ]);
            }
        }

        // 2. Nếu chưa bị ép lấy Bot -> Tìm người chơi thật đang chờ
        if (!$forceBot) {
            // Tìm đối thủ trong hàng chờ (bỏ qua bản thân)
            $matchedOpponent = DB::table('rank_queues')
                ->where('user_id', '!=', $user->id)
                ->where('status', 'waiting')
                ->orderBy('id', 'asc')
                ->first();

            if ($matchedOpponent) {
                $opponentUser = User::find($matchedOpponent->user_id);
                $roomCode = 'RANK-' . strtoupper(Str::random(5));

                // Tạo phòng thi đấu PvP Rank online chính thức
                $room = Room::create([
                    'room_code'         => $roomCode,
                    'player1_id'        => $matchedOpponent->user_id,
                    'player2_id'        => $user->id,
                    'status'            => 'setup',
                    'current_turn'      => 'player1', // Khởi tạo sẵn giá trị hợp lệ
                    'p1_shots'          => [],
                    'p2_shots'          => [],
                    'p1_active_skills'  => [],
                    'p2_active_skills'  => [],
                ]);

                // Đánh dấu cho người chơi thứ nhất biết đã ghép thành công
                DB::table('rank_queues')->where('id', $matchedOpponent->id)->update([
                    'status'    => 'matched',
                    'room_code' => $roomCode,
                ]);

                // Xóa chính mình khỏi hàng chờ
                DB::table('rank_queues')->where('user_id', $user->id)->delete();

                return response()->json([
                    'status'     => 'matched_real',
                    'room'       => $room,
                    'room_code'  => $roomCode,
                    'role'       => 'player2',
                    'opponent'   => [
                        'name'   => $opponentUser->name ?? 'Chỉ Huy Trực Tuyến',
                        'is_bot' => false,
                        'elo'    => $opponentUser->elo ?? 500,
                    ],
                ]);
            }

            // Nếu chưa có ai, đưa bản thân vào hàng chờ
            DB::table('rank_queues')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'tier'       => $userTier,
                    'elo'        => $user->elo ?? 500,
                    'status'     => 'waiting',
                    'room_code'  => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            return response()->json(['status' => 'waiting']);
        }

        // 3. Đúng khi hết 25s (forceBot = true) mới chọn Bot
        DB::table('rank_queues')->where('user_id', $user->id)->delete();

        $candidates = User::where('is_bot', true)
            ->where('rank_tier', $userTier)
            ->inRandomOrder()
            ->take(5)
            ->get();

        if ($candidates->isEmpty()) {
            $candidates = User::where('is_bot', true)->inRandomOrder()->take(5)->get();
        }

        $bot = $candidates->random();
        $isSmurf = in_array($bot->bot_difficulty, ['hard', 'nightmare']) && in_array($userTier, ['seaman', 'petty_officer']);

        return response()->json([
            'status'   => 'matched_bot',
            'opponent' => [
                'id'             => $bot->id,
                'name'           => $bot->name,
                'elo'            => $bot->elo,
                'rank_tier'      => $bot->rank_tier,
                'is_bot'         => true,
                'is_smurf'       => $isSmurf,
                'bot_difficulty' => $isSmurf ? 'nightmare' : ($bot->bot_difficulty ?? 'medium'),
            ],
        ]);
    }

    public function cancelMatchmake(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            DB::table('rank_queues')->where('user_id', $user->id)->delete();
        }
        return response()->json(['status' => 'cancelled']);
    }

    public function recordResult(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $opponentRank = (int) $request->input('opponent_rank', 99);
        $isWin = (bool) $request->input('is_win', true);
        $gainedElo = (int) $request->input('gained_elo', 20);

        if ($isWin) {
            $user->elo = ($user->elo ?? 500) + $gainedElo;
            $user->pvp_wins = ($user->pvp_wins ?? 0) + 1;

            if ($opponentRank <= 5) {
                \App\Http\Controllers\AchievementController::unlock($user, 'pvp_beat_top5');
            }
        } else {
            $user->elo = max(100, ($user->elo ?? 500) - 15);
            $user->pvp_losses = ($user->pvp_losses ?? 0) + 1;
        }

        $user->rank_tier = self::calculateTier($user->elo);
        $user->save();

        return response()->json([
            'status'    => 'success',
            'elo'       => $user->elo,
            'rank_tier' => $user->rank_tier,
        ]);
    }

    protected function simulateBackgroundBotMatches(): void
    {
        $bots = User::where('is_bot', true)->inRandomOrder()->take(6)->get();
        if ($bots->count() < 2) return;

        foreach ($bots as $bot) {
            $isSmurf = in_array($bot->bot_difficulty, ['hard', 'nightmare']);
            $currentTier = $bot->rank_tier;
            $tierCfg = self::RANK_TIERS[$currentTier] ?? self::RANK_TIERS['seaman'];

            $shouldSmurfThrow = false;
            if ($isSmurf) {
                if (in_array($currentTier, ['seaman', 'petty_officer', 'ensign']) && ($bot->elo >= ($tierCfg['max_elo'] - 60))) {
                    $shouldSmurfThrow = true;
                } elseif ($currentTier === 'ensign' && rand(1, 100) <= 65) {
                    $shouldSmurfThrow = true;
                }
            }

            if ($shouldSmurfThrow) {
                $bot->elo = max(150, $bot->elo - rand(22, 38));
                $bot->pvp_losses++;
            } else {
                $winChance = match ($bot->bot_difficulty) {
                    'nightmare' => 85,
                    'hard'      => 70,
                    'medium'    => 52,
                    default     => 35,
                };

                if (rand(1, 100) <= $winChance) {
                    $bot->elo += rand(14, 25);
                    $bot->pvp_wins++;
                } else {
                    $bot->elo = max(100, $bot->elo - rand(12, 22));
                    $bot->pvp_losses++;
                }
            }

            $bot->rank_tier = $this->calculateTier($bot->elo);
            $bot->save();
        }
    }

    public static function calculateTier(int $elo): string
    {
        if ($elo >= 3000) return 'fleet_admiral';
        if ($elo >= 2500) return 'captain';
        if ($elo >= 2000) return 'lieutenant';
        if ($elo >= 1500) return 'ensign';
        if ($elo >= 1000) return 'petty_officer';
        return 'seaman';
    }
}