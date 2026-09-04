<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    public const ITEMS = [
        'recon_sat' => ['name' => 'Vệ Tinh Quét Vị Trí', 'category' => 'recon', 'tier' => 'strong', 'price' => 250, 'gem_price' => 35, 'daily_limit' => 1, 'desc' => 'Lộ chính xác 1 ô có tàu địch còn sống + tên tàu.'],
        'recon_scan' => ['name' => 'Radar Vùng 3x3', 'category' => 'recon', 'tier' => 'medium', 'price' => 100, 'gem_price' => 18, 'daily_limit' => 3, 'desc' => 'Quét vùng 3x3: Báo tổng số ô chứa tàu địch.'],
        'recon_sonar' => ['name' => 'Sonar Cảm Biến 5x5', 'category' => 'recon', 'tier' => 'weak', 'price' => 40, 'gem_price' => 8, 'daily_limit' => 5, 'desc' => 'Quét bán kính 5x5: Báo Rất gần, Gần, Xa, hoặc Rất xa.'],

        'combat_guided' => ['name' => 'Tên Lửa Dẫn Đường', 'category' => 'combat', 'tier' => 'strong', 'price' => 300, 'gem_price' => 35, 'daily_limit' => 1, 'desc' => 'Lập tức bắn trúng 1 ô tàu địch ngẫu nhiên + báo tên tàu.'],
        'combat_airstrike' => ['name' => 'Không Kích Phá Rối', 'category' => 'combat', 'tier' => 'medium', 'price' => 150, 'gem_price' => 18, 'daily_limit' => 2, 'desc' => 'Nếu Carrier còn nổi: Địch mất 1 lượt phản công tiếp theo.'],
        'combat_smokescreen' => ['name' => 'Màn Khói Nhiễu Loạn', 'category' => 'combat', 'tier' => 'weak', 'price' => 60, 'gem_price' => 8, 'daily_limit' => 4, 'desc' => 'Địch không thể biết trúng hay trượt trong 5 lượt tiếp theo.'],

        'def_shield' => ['name' => 'Khiên Năng Lượng Tàu', 'category' => 'defend', 'tier' => 'strong', 'price' => 280, 'gem_price' => 35, 'daily_limit' => 1, 'desc' => 'Chọn 1 tàu: Chặn 3 phát đạn trúng tiếp theo vào tàu đó.'],
        'def_repair_relocate' => ['name' => 'Tái Cấu Trúc Khẩn Cấp', 'category' => 'defend', 'tier' => 'medium', 'price' => 160, 'gem_price' => 18, 'daily_limit' => 2, 'desc' => 'Sửa lành toàn bộ hư hại của 1 tàu và dịch chuyển sang ô trống.'],
        'def_tactical_relocate' => ['name' => 'Cơ Động Chiến Thuật', 'category' => 'defend', 'tier' => 'weak', 'price' => 70, 'gem_price' => 8, 'daily_limit' => 3, 'desc' => 'Dịch chuyển 1 tàu còn nguyên vẹn đến vị trí ngẫu nhiên mới.'],
    ];

    protected function formatCoord(int $x, int $y): string
    {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $letter = $letters[$y] ?? '?';
        $num = $x + 1;
        return "{$letter}{$num}";
    }

    /**
     * Mua Power-up bằng Tiền Credits ($) có giới hạn ngày
     */
    public function buy(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập quân tịch!'], 401);
        }

        $itemId = $request->input('item_id');
        if (!isset(self::ITEMS[$itemId])) {
            return response()->json(['error' => 'Vật phẩm không hợp lệ!'], 422);
        }

        $itemSpec = self::ITEMS[$itemId];
        $today = now()->toDateString();
        $daily = $user->daily_purchases ?? ['date' => $today, 'items' => []];

        if (($daily['date'] ?? '') !== $today) {
            $daily = ['date' => $today, 'items' => []];
        }

        $boughtToday = $daily['items'][$itemId] ?? 0;
        if ($boughtToday >= $itemSpec['daily_limit']) {
            return response()->json(['error' => "Đã đạt giới hạn mua hôm nay ({$itemSpec['daily_limit']}/ngày)!"], 400);
        }

        if (($user->credits ?? 0) < $itemSpec['price']) {
            return response()->json(['error' => 'Ngân sách không đủ để trang bị vật phẩm này!'], 400);
        }

        $user->credits -= $itemSpec['price'];
        $inv = $user->inventory ?? [];
        $inv[$itemId] = ($inv[$itemId] ?? 0) + 1;

        $daily['items'][$itemId] = $boughtToday + 1;

        $user->inventory = $inv;
        $user->daily_purchases = $daily;
        $user->save();

        return response()->json([
            'status'          => 'success',
            'credits'         => $user->credits,
            'gems'            => $user->gems ?? 0,
            'inventory'       => $user->inventory,
            'daily_purchases' => $user->daily_purchases,
            'message'         => "Đã trang bị {$itemSpec['name']} thành công!",
        ]);
    }

    /**
     * Mua Power-up bằng Gems (💎) - KHÔNG GIỚI HẠN SỐ LƯỢNG & THỜI GIAN
     */
    public function buyWithGems(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập quân tịch!'], 401);
        }

        $itemId = $request->input('item_id');
        if (!isset(self::ITEMS[$itemId])) {
            return response()->json(['error' => 'Vật phẩm không hợp lệ!'], 422);
        }

        $itemSpec = self::ITEMS[$itemId];
        $gemCost = $itemSpec['gem_price'];

        if (($user->gems ?? 0) < $gemCost) {
            return response()->json(['error' => "Bạn cần tối thiểu {$gemCost} Gems để sở hữu vật phẩm này!"], 400);
        }

        $user->gems -= $gemCost;
        $inv = $user->inventory ?? [];
        $inv[$itemId] = ($inv[$itemId] ?? 0) + 1;
        $user->inventory = $inv;
        $user->save();

        return response()->json([
            'status'          => 'success',
            'credits'         => $user->credits,
            'gems'            => $user->gems,
            'inventory'       => $user->inventory,
            'daily_purchases' => $user->daily_purchases,
            'message'         => "Mua thành công {$itemSpec['name']} từ Chợ Đen Vô Cực!",
        ]);
    }

    /**
     * Dùng 25 Gems để Reset kho hàng ngày (Restock Refresh)
     */
    public function resetDailyRestock(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập quân tịch!'], 401);
        }

        if (($user->gems ?? 0) < 25) {
            return response()->json(['error' => 'Cần tối thiểu 25 Gems để làm mới toàn bộ kho hàng!'], 400);
        }

        $user->gems -= 25;
        // Xóa sạch bộ đếm mua hôm nay
        $user->daily_purchases = ['date' => now()->toDateString(), 'items' => []];
        $user->save();

        return response()->json([
            'status'          => 'success',
            'gems'            => $user->gems,
            'credits'         => $user->credits,
            'daily_purchases' => $user->daily_purchases,
            'message'         => 'Đã làm mới toàn bộ lượt mua hàng hôm nay thành công!',
        ]);
    }

    public function use(Request $request, Game $game): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chỉ huy chưa đăng nhập!'], 401);
        }

        if ($game->status !== 'playing' || $game->current_turn !== 'player') {
            return response()->json(['error' => 'Không thể sử dụng kỹ năng vào lúc này!'], 400);
        }

        $itemId = $request->input('item_id');
        $inv = $user->inventory ?? [];

        if (($inv[$itemId] ?? 0) <= 0) {
            return response()->json(['error' => 'Bạn không sở hữu vật phẩm tác chiến này!'], 400);
        }

        $mem = $game->bot_memory ?? [];
        $playerUsedPowers = $mem['player_used_powers'] ?? [];

        if (in_array($itemId, $playerUsedPowers)) {
            return response()->json(['error' => 'Kỹ năng này bạn đã sử dụng trong trận này rồi!'], 400);
        }

        $itemCategory = self::ITEMS[$itemId]['category'] ?? null;
        $playerUsedCategories = $mem['player_used_categories'] ?? [];

        if ($itemCategory && in_array($itemCategory, $playerUsedCategories)) {
            $catNames = ['recon' => 'Trinh Sát (Recon)', 'combat' => 'Tác Chiến (Combat)', 'defend' => 'Phòng Thủ (Defend)'];
            $catLabel = $catNames[$itemCategory] ?? $itemCategory;
            return response()->json(['error' => "Mỗi trận bạn chỉ được kích hoạt 1 kỹ năng thuộc mục [{$catLabel}]!"], 400);
        }

        $effectResult = [];
        $skipBotCounterAttack = false;
        $updatedPlayerShips = null;

        $targetX = (int) $request->input('target_x', 4);
        $targetY = (int) $request->input('target_y', 4);
        $targetShipName = $request->input('target_ship_name');

        switch ($itemId) {
            case 'recon_sat':
                $fired = [];
                foreach ($game->bot_shots ?? [] as $s) $fired["{$s['x']},{$s['y']}"] = true;

                $found = null;
                foreach ($game->bot_ships as $ship) {
                    if ($ship['hits'] < $ship['size']) {
                        foreach ($ship['coordinates'] as $c) {
                            if (!isset($fired["{$c['x']},{$c['y']}"])) {
                                $found = ['x' => $c['x'], 'y' => $c['y'], 'ship' => $ship['name']];
                                break 2;
                            }
                        }
                    }
                }
                if ($found) {
                    $coordLabel = $this->formatCoord($found['x'], $found['y']);
                    $effectResult = ['type' => 'reveal', 'x' => $found['x'], 'y' => $found['y'], 'ship' => $found['ship'], 'msg' => "Vệ tinh phát hiện tàu [{$found['ship']}] tại ô [{$coordLabel}]!"];
                } else {
                    $effectResult = ['msg' => 'Toàn bộ tàu địch đã bị phát hiện hoặc đánh chìm.'];
                }
                break;

            case 'recon_scan':
                $count = 0;
                $enemyCoords = [];
                foreach ($game->bot_ships as $s) {
                    foreach ($s['coordinates'] as $c) $enemyCoords["{$c['x']},{$c['y']}"] = true;
                }
                for ($dx = -1; $dx <= 1; $dx++) {
                    for ($dy = -1; $dy <= 1; $dy++) {
                        $tx = $targetX + $dx; $ty = $targetY + $dy;
                        if ($tx >= 0 && $tx < 10 && $ty >= 0 && $ty < 10 && isset($enemyCoords["$tx,$ty"])) {
                            $count++;
                        }
                    }
                }
                $centerLabel = $this->formatCoord($targetX, $targetY);
                $effectResult = [
                    'type'    => 'scan_3x3',
                    'cx'      => $targetX,
                    'cy'      => $targetY,
                    'count'   => $count,
                    'msg'     => "Radar quét vùng 3x3 quanh tâm [{$centerLabel}] xác nhận có {$count} ô chứa tàu đối phương!"
                ];
                break;

            case 'recon_sonar':
                $minDistance = 999;
                foreach ($game->bot_ships as $s) {
                    if ($s['hits'] < $s['size']) {
                        foreach ($s['coordinates'] as $c) {
                            $dist = abs($c['x'] - $targetX) + abs($c['y'] - $targetY);
                            if ($dist < $minDistance) $minDistance = $dist;
                        }
                    }
                }

                $desc = 'RẤT XA (> 5 ô)';
                if ($minDistance <= 1) $desc = 'RẤT GẦN (Ngay sát cạnh!)';
                elseif ($minDistance <= 2) $desc = 'GẦN (Cách khoảng 2 ô)';
                elseif ($minDistance <= 4) $desc = 'XA (Cách 3-4 ô)';

                $centerLabel = $this->formatCoord($targetX, $targetY);
                $effectResult = [
                    'type'  => 'sonar_5x5',
                    'cx'    => $targetX,
                    'cy'    => $targetY,
                    'msg'   => "Sonar quét quanh tâm [{$centerLabel}]: Mục tiêu gần nhất đang ở cự ly [{$desc}]!"
                ];
                break;

            case 'combat_guided':
                $fired = [];
                foreach ($game->bot_shots ?? [] as $s) $fired["{$s['x']},{$s['y']}"] = true;
                $target = null;

                foreach ($game->bot_ships as $ship) {
                    if ($ship['hits'] < $ship['size']) {
                        foreach ($ship['coordinates'] as $c) {
                            if (!isset($fired["{$c['x']},{$c['y']}"])) {
                                $target = $c;
                                break 2;
                            }
                        }
                    }
                }

                if ($target) {
                    $inv[$itemId]--;
                    $user->inventory = $inv;
                    $user->save();

                    $playerUsedPowers[] = $itemId;
                    if ($itemCategory) $playerUsedCategories[] = $itemCategory;
                    $mem['player_used_powers'] = $playerUsedPowers;
                    $mem['player_used_categories'] = $playerUsedCategories;
                    $game->bot_memory = $mem;
                    $game->save();

                    $fakeReq = new Request(['x' => $target['x'], 'y' => $target['y']]);
                    return app(GameController::class)->fire($fakeReq, $game);
                }
                $effectResult = ['msg' => 'Không còn mục tiêu khả dụng để phóng tên lửa.'];
                break;

            case 'combat_airstrike':
                $carrierAlive = false;
                foreach ($game->player_ships as $s) {
                    if ($s['name'] === 'Carrier' && $s['hits'] < $s['size']) $carrierAlive = true;
                }
                if (!$carrierAlive) {
                    return response()->json(['error' => 'Tàu sân bay Carrier của bạn đã chìm hoặc hư hại nặng!'], 400);
                }
                $skipBotCounterAttack = true;
                $mem['skip_next_turn'] = true;
                $effectResult = ['type' => 'airstrike', 'msg' => 'Không kích thành công! Hệ thống hỏa lực đối phương bị tê liệt hoàn toàn (MẤT LƯỢT)!'];
                break;

            case 'combat_smokescreen':
                $mem['player_smoke_turns'] = 5;
                $effectResult = ['type' => 'smoke', 'msg' => 'Màn khói mù mịt đã triển khai! Toàn bộ vị trí hạm đội của bạn được ngụy trang.'];
                break;

            case 'def_shield':
                $mem['player_shield_charges'] = 3;
                $mem['player_shield_turns'] = 5; 
                $effectResult = ['type' => 'shield', 'msg' => 'Khiên Năng Lượng đã kích hoạt! Miễn nhiễm 3 phát đạn trúng trong vòng 5 lượt tới!'];
                break;

            case 'def_repair_relocate':
                $ships = $game->player_ships;
                $targetIndex = null;

                foreach ($ships as $idx => $s) {
                    if (($targetShipName && $s['name'] === $targetShipName && $s['hits'] > 0) || (!$targetShipName && $s['hits'] > 0)) {
                        $targetIndex = $idx;
                        break;
                    }
                }

                if ($targetIndex === null) {
                    return response()->json(['error' => 'Tàu được chọn không bị thương hoặc không tìm thấy tàu cần phục hồi!'], 400);
                }

                $ships[$targetIndex]['hits'] = 0;
                $oldCoords = $ships[$targetIndex]['coordinates'];

                $cleanedShots = array_filter($game->player_shots ?? [], function($shot) use ($oldCoords) {
                    foreach ($oldCoords as $c) {
                        if ($c['x'] === $shot['x'] && $c['y'] === $shot['y']) return false;
                    }
                    return true;
                });
                $game->player_shots = array_values($cleanedShots);

                $newPlacement = $this->relocateShipSafely($ships, $targetIndex, $game->player_shots);
                if ($newPlacement) $ships = $newPlacement;

                $game->player_ships = $ships;
                $updatedPlayerShips = $ships;
                $effectResult = [
                    'type'  => 'relocated',
                    'ships' => $ships,
                    'msg'   => "Tàu [{$ships[$targetIndex]['name']}] đã sửa chữa 100% và cơ động thành công sang vị trí mới!"
                ];
                break;

            case 'def_tactical_relocate':
                $ships = $game->player_ships;
                $targetIndex = null;

                foreach ($ships as $idx => $s) {
                    if (($targetShipName && $s['name'] === $targetShipName && $s['hits'] === 0) || (!$targetShipName && $s['hits'] === 0)) {
                        $targetIndex = $idx;
                        break;
                    }
                }

                if ($targetIndex === null) {
                    return response()->json(['error' => 'Tàu được chọn đã bị thương hoặc không hợp lệ!'], 400);
                }

                $newPlacement = $this->relocateShipSafely($ships, $targetIndex, $game->player_shots ?? []);
                if (!$newPlacement) {
                    return response()->json(['error' => 'Hải đồ không còn đủ khoảng trống an toàn để di chuyển tàu!'], 400);
                }

                $game->player_ships = $newPlacement;
                $updatedPlayerShips = $newPlacement;
                $effectResult = [
                    'type'  => 'relocated',
                    'ships' => $newPlacement,
                    'msg'   => "Tàu [{$ships[$targetIndex]['name']}] đã bí mật cơ động sang tọa độ mới!"
                ];
                break;
        }

        $inv[$itemId]--;
        $user->inventory = $inv;
        $user->save();

        $playerUsedPowers[] = $itemId;
        if ($itemCategory && !in_array($itemCategory, $playerUsedCategories)) {
            $playerUsedCategories[] = $itemCategory;
        }
        $mem['player_used_powers'] = $playerUsedPowers;
        $mem['player_used_categories'] = $playerUsedCategories;
        $game->bot_memory = $mem;

        $botTurn = null;
        if (!$skipBotCounterAttack) {
            $botTurn = app(GameController::class)->executeBotTurn($game);
        }

        $game->save();

        return response()->json([
            'status'         => 'success',
            'inventory'      => $user->inventory,
            'effect'         => $effectResult,
            'bot_shot'       => $botTurn,
            'player_ships'   => $updatedPlayerShips,
            'game_status'    => $game->status,
            'current_turn'   => $game->current_turn,
        ]);
    }

    protected function relocateShipSafely(array $allShips, int $targetIndex, array $firedShots): ?array
    {
        $targetShip = $allShips[$targetIndex];
        $size = $targetShip['size'];
        $firedKeys = [];
        foreach ($firedShots as $fs) $firedKeys["{$fs['x']},{$fs['y']}"] = true;

        for ($attempt = 0; $attempt < 150; $attempt++) {
            $isHorizontal = (bool) rand(0, 1);
            $maxX = $isHorizontal ? (10 - $size) : 9;
            $maxY = $isHorizontal ? 9 : (10 - $size);
            $startX = rand(0, $maxX);
            $startY = rand(0, $maxY);

            $newCoords = [];
            $collided = false;

            for ($i = 0; $i < $size; $i++) {
                $curX = $isHorizontal ? $startX + $i : $startX;
                $curY = $isHorizontal ? $startY : $startY + $i;

                if (isset($firedKeys["$curX,$curY"])) {
                    $collided = true;
                    break;
                }

                foreach ($allShips as $idx => $s) {
                    if ($idx === $targetIndex) continue;
                    foreach ($s['coordinates'] as $c) {
                        if ($c['x'] === $curX && $c['y'] === $curY) {
                            $collided = true;
                            break 2;
                        }
                    }
                }
                $newCoords[] = ['x' => $curX, 'y' => $curY];
            }

            if (!$collided) {
                $allShips[$targetIndex]['coordinates'] = $newCoords;
                return $allShips;
            }
        }
        return null;
    }
}