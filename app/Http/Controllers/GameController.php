<?php

namespace App\Http\Controllers;

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

    /**
     * Tạo ván game mới (Mặc định xếp tàu ngẫu nhiên cho cả 2 bên)
     */
    public function create(Request $request): JsonResponse
    {
        $difficulty = $request->input('difficulty', 'easy');
        $mode = $request->input('mode', 'pve');
        $customPlayerShips = $request->input('player_ships');

        // Nếu người chơi gửi mảng tàu đã tự dàn trận lên thì dùng, ngược lại tự sinh
        $playerShips = !empty($customPlayerShips) 
            ? $customPlayerShips 
            : $this->botService->placeFleetRandomly();

        $botShips = $this->botService->placeFleetRandomly();

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
            'bot_memory'   => ['targets' => []],
            'started_at'   => now(),
        ]);

        return response()->json([
            'game_id'      => $game->id,
            'status'       => $game->status,
            'difficulty'   => $game->difficulty,
            'player_ships' => $game->player_ships,
        ]);
    }

    /**
     * Xử lý lượt bắn của Player vào bàn cờ của Bot
     */
    public function fire(Request $request, Game $game): JsonResponse
    {
        if ($game->status !== 'playing' || $game->current_turn !== 'player') {
            return response()->json(['error' => 'Chưa đến lượt hoặc game đã kết thúc.'], 400);
        }

        $x = (int) $request->input('x');
        $y = (int) $request->input('y');

        // Kiểm tra xem ô này đã bắn trước đó chưa
        $botShots = $game->bot_shots ?? [];
        foreach ($botShots as $shot) {
            if ($shot['x'] === $x && $shot['y'] === $y) {
                return response()->json(['error' => 'Tọa độ này đã được bắn trước đó!'], 422);
            }
        }

        // Kiểm tra trúng hay trượt trên hạm đội của Bot
        $hitResult = $this->processShot($game->bot_ships, $x, $y);
        $botShots[] = [
            'x'      => $x,
            'y'      => $y,
            'result' => $hitResult['result'],
            'ship'   => $hitResult['ship_name'] ?? null,
        ];

        $game->bot_ships = $hitResult['ships'];
        $game->bot_shots = $botShots;

        // Kiểm tra Player đã thắng chưa (toàn bộ tàu bot đã chìm)
        if ($this->isFleetDestroyed($game->bot_ships)) {
            $game->status = 'won';
            $game->save();

            $stats = $this->calculateScore($game);

            return response()->json([
                'player_shot' => end($botShots),
                'game_status' => 'won',
                'stats'       => $stats,
                'message'     => 'Chúc mừng! Bạn đã bắn chìm toàn bộ hạm đội đối phương!',
            ]);
        }

        // Nếu bắn trượt, chuyển lượt cho Bot phản công
        $botTurnResult = null;
        if ($hitResult['result'] === 'miss') {
            $game->current_turn = 'bot';
            $botTurnResult = $this->executeBotTurn($game);
        }

        $game->save();

        return response()->json([
            'player_shot'  => end($botShots),
            'bot_shot'     => $botTurnResult,
            'game_status'  => $game->status,
            'current_turn' => $game->current_turn,
        ]);
    }

    /**
     * Thực thi lượt bắn tự động của Bot
     */
    protected function executeBotTurn(Game $game): array
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

        $hitResult = $this->processShot($game->player_ships, $x, $y);
        $playerShots = $game->player_shots ?? [];

        $playerShots[] = [
            'x'      => $x,
            'y'      => $y,
            'result' => $hitResult['result'],
            'ship'   => $hitResult['ship_name'] ?? null,
        ];

        // Cập nhật trí nhớ cho bot nếu bắn trúng
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
        $game->bot_memory = $memory;

        if ($this->isFleetDestroyed($game->player_ships)) {
            $game->status = 'lost';
        } else {
            $game->current_turn = 'player';
        }

        return end($playerShots);
    }

    /**
     * Cập nhật số điểm trúng của tàu khi trúng đạn
     */
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

    /**
     * Kiểm tra toàn bộ hạm đội đã bị tiêu diệt hết chưa
     */
    protected function isFleetDestroyed(array $ships): bool
    {
        foreach ($ships as $ship) {
            if ($ship['hits'] < $ship['size']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Tính toán chi tiết điểm số khi trận đấu kết thúc
     */
    protected function calculateScore(Game $game): array
    {
        $startTime = $game->started_at ? Carbon::parse($game->started_at) : Carbon::parse($game->created_at);
        $duration = max(1, (int) $startTime->diffInSeconds(now()));
        
        // 1. Tỉ lệ trúng đạn
        $botShots = $game->bot_shots ?? [];
        $totalFired = count($botShots);
        $hitCount = count(array_filter($botShots, fn($s) => in_array($s['result'], ['hit', 'sunk'])));
        $accuracy = $totalFired > 0 ? round(($hitCount / $totalFired) * 100, 1) : 0;

        // 2. Bảo toàn hạm đội và tính điểm tàu giá trị cao
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

        // 3. Thời gian (Tối đa 300 giây)
        $timeBonus = max(0, (300 - $duration)) * 10;
        $accuracyBonus = round(($accuracy / 100) * 2000);

        // 4. Hệ số độ khó
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

    /**
     * Lấy Top 5 cao điểm nhất của từng cấp độ máy
     */
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

    /**
     * Lưu kỷ lục người chơi vào Bảng xếp hạng
     */
    public function saveScore(Request $request, Game $game): JsonResponse
    {
        $name = trim($request->input('player_name', 'Chỉ Huy Ẩn Danh')) ?: 'Chỉ Huy Ẩn Danh';
        $stats = $this->calculateScore($game);

        $record = Leaderboard::create([
            'player_name'      => substr($name, 0, 20),
            'difficulty'       => $game->difficulty,
            'score'            => $stats['score'],
            'duration_seconds' => $stats['duration_seconds'],
            'accuracy'         => $stats['accuracy'],
            'fleet_health'     => $stats['fleet_health'],
        ]);

        return response()->json([
            'status' => 'success',
            'record' => $record,
        ]);
    }
}