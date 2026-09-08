<?php

namespace App\Http\Controllers;

use App\Events\PvpGameStarted;
use App\Events\PvpShotFired;
use App\Events\PvpSkillUsed;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\AchievementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PvpController extends Controller
{
    public function createRoom(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập quân tịch!'], 401);
        }

        $roomCode = 'ROOM-' . strtoupper(Str::random(5));

        $room = Room::create([
            'room_code'  => $roomCode,
            'player1_id' => $user->id,
            'status'     => 'waiting',
        ]);

        return response()->json([
            'status'    => 'success',
            'room_code' => $room->room_code,
            'room_id'   => $room->id,
            'message'   => 'Đã tạo phòng chờ thành công!',
        ]);
    }

    public function joinRoom(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập quân tịch!'], 401);
        }

        $roomCode = strtoupper(trim($request->input('room_code')));
        $room = Room::where('room_code', $roomCode)->first();

        if (!$room) {
            return response()->json(['error' => 'Không tìm thấy mã phòng tác chiến này!'], 404);
        }

        // Nếu chính chủ phòng bấm vào lại phòng của mình
        if ($room->player1_id === $user->id) {
            return response()->json(['status' => 'success', 'room' => $room, 'role' => 'player1']);
        }

        // Nếu phòng đã đủ 2 người và đúng là người chơi thứ 2 quay lại
        if ($room->player2_id === $user->id) {
            return response()->json(['status' => 'success', 'room' => $room, 'role' => 'player2']);
        }

        // Nếu phòng đã có người khác chiếm chỗ
        if ($room->player2_id && $room->player2_id !== $user->id) {
            return response()->json(['error' => 'Phòng đấu đã đủ 2 chỉ huy!'], 400);
        }

        if ($room->status !== 'waiting' && $room->status !== 'setup') {
            return response()->json(['error' => 'Phòng này đã bắt đầu trận chiến hoặc đã đóng!'], 400);
        }

        // GÁN NGAY LẬP TỨC PLAYER 2 VÀ ĐỔI TRẠNG THÁI PHÒNG
        $room->player2_id = $user->id;
        $room->status = 'setup'; 
        $room->save();

        try {
            broadcast(new \App\Events\PlayerJoinedRoom($room))->toOthers();
        } catch (\Exception $e) {}

        return response()->json([
            'status'  => 'success',
            'room'    => $room,
            'role'    => 'player2',
            'message' => 'Đã gia nhập phòng chiến thành công!',
        ]);
    }

    public function ready(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $roomCode = $request->input('room_code');
        $ships = $request->input('ships');

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room) {
            return response()->json(['error' => 'Không tìm thấy phòng!'], 404);
        }

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);

        if (!$isP1 && !$isP2) {
            return response()->json(['error' => 'Bạn không thuộc phòng đấu này!'], 403);
        }

        if ($isP1) {
            $room->p1_ships = $ships;
            $room->p1_ready = true;
        } else {
            $room->p2_ships = $ships;
            $room->p2_ready = true;
        }

        if ($room->p1_ready && $room->p2_ready) {
            $room->status = 'rps_pending';
            $room->save();

            event(new PvpGameStarted($room));

            return response()->json([
                'status'     => 'both_ready',
                'room'       => $room,
                'is_started' => true,
            ]);
        }

        $room->save();

        return response()->json([
            'status'     => 'waiting_opponent',
            'room'       => $room,
            'is_started' => false,
            'message'    => 'Bạn đã sẵn sàng! Đang chờ đối thủ đặt tàu...',
        ]);
    }

    public function fire(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $roomCode = $request->input('room_code');
        $isTimeout = (bool) $request->input('timeout', false);

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room || !in_array($room->status, ['playing', 'setup'])) {
            return response()->json(['error' => 'Trận đấu không tồn tại hoặc đã kết thúc!'], 400);
        }

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);

        if (!$isP1 && !$isP2) {
            return response()->json(['error' => 'Bạn không thuộc phòng này!'], 403);
        }

        $myRole = $isP1 ? 'player1' : 'player2';
        $enemyRole = $isP1 ? 'player2' : 'player1';

        if ($room->current_turn !== $myRole) {
            return response()->json(['error' => 'Chưa đến lượt của bạn!'], 400);
        }

        // HẾT GIỜ TỰ ĐỘNG CHUYỂN LƯỢT
        if ($isTimeout) {
            $room->current_turn = $enemyRole;
            $room->save();

            $shotPayload = [
                'shooter_role' => $myRole,
                'is_timeout'   => true,
                'next_turn'    => $enemyRole,
                'status'       => 'playing',
                'msg'          => "Chỉ huy [{$user->name}] đã quá thời gian tác chiến (15s)! BỊ MẤT LƯỢT!",
            ];

            event(new PvpShotFired($room, $shotPayload));

            return response()->json([
                'status' => 'timeout',
                'shot'   => $shotPayload,
                'room'   => $room,
            ]);
        }

        $x = (int) $request->input('x');
        $y = (int) $request->input('y');

        $myShots = ($isP1 ? $room->p1_shots : $room->p2_shots) ?? [];
        foreach ($myShots as $shot) {
            if ($shot['x'] === $x && $shot['y'] === $y) {
                return response()->json(['error' => 'Tọa độ này đã bắn rồi!'], 422);
            }
        }

        $enemyShips = ($isP1 ? $room->p2_ships : $room->p1_ships) ?? [];
        $enemyActive = ($isP1 ? $room->p2_active_skills : $room->p1_active_skills) ?? [];

        $result = 'miss';
        $hitShipName = null;

        foreach ($enemyShips as &$ship) {
            foreach ($ship['coordinates'] as $coord) {
                if ($coord['x'] === $x && $coord['y'] === $y) {
                    $result = 'hit';
                    $ship['hits'] = ($ship['hits'] ?? 0) + 1;
                    $hitShipName = $ship['name'];
                    if ($ship['hits'] >= $ship['size']) {
                        $result = 'sunk';
                    }
                    break 2;
                }
            }
        }

        // Khiên Năng Lượng
        if (($enemyActive['shield_charges'] ?? 0) > 0 && in_array($result, ['hit', 'sunk'])) {
            $enemyActive['shield_charges']--;
            $result = 'shield_blocked';
            if ($hitShipName) {
                foreach ($enemyShips as &$s) {
                    if ($s['name'] === $hitShipName && ($s['hits'] ?? 0) > 0) {
                        $s['hits']--;
                        break;
                    }
                }
            }
        }

        // Màn Khói
        $displayedResult = $result;
        if (($enemyActive['smoke_turns'] ?? 0) > 0) {
            $enemyActive['smoke_turns']--;
            $displayedResult = 'smoke_hidden';
        }

        if ($isP1) {
            $room->p2_active_skills = $enemyActive;
        } else {
            $room->p1_active_skills = $enemyActive;
        }

        $myShots[] = ['x' => $x, 'y' => $y, 'result' => $result, 'ship' => $hitShipName];

        if ($isP1) {
            $room->p1_shots = $myShots;
            $room->p2_ships = $enemyShips;
        } else {
            $room->p2_shots = $myShots;
            $room->p1_ships = $enemyShips;
        }

        // Kiểm tra toàn bộ hạm đội đối thủ
        $hitShotMap = [];
        foreach ($myShots as $shot) {
            if (in_array($shot['result'] ?? '', ['hit', 'sunk'])) {
                $hitShotMap["{$shot['x']},{$shot['y']}"] = true;
            }
        }

        $enemyDestroyed = true;
        foreach ($enemyShips as &$s) {
            $shipHitCount = 0;
            $shipCoords = $s['coordinates'] ?? [];
            $shipSize = count($shipCoords);

            foreach ($shipCoords as $coord) {
                if (isset($hitShotMap["{$coord['x']},{$coord['y']}"])) {
                    $shipHitCount++;
                }
            }

            // Đồng bộ lại chuẩn xác số hit thực tế của tàu
            $s['hits'] = $shipHitCount;

            if ($shipHitCount < $shipSize) {
                $enemyDestroyed = false;
            }
        }
        unset($s);

        if ($isP1) {
            $room->p2_ships = $enemyShips;
        } else {
            $room->p1_ships = $enemyShips;
        }

        if ($enemyDestroyed) {
            $room->status = 'finished';
            $room->winner = (string) $myRole;

            // MỞ KHÓA THÀNH TỰU KHI THẮNG TRẬN PVP
            AchievementController::unlock($user, 'pvp_first_win');

            $myRemainingShips = 0;
            $myCurrentShips = ($isP1 ? $room->p1_ships : $room->p2_ships) ?? [];
            foreach ($myCurrentShips as $ms) {
                if (($ms['hits'] ?? 0) < $ms['size']) $myRemainingShips++;
            }
            if ($myRemainingShips === 1) {
                AchievementController::unlock($user, 'pvp_comeback');
            }
        } else {
            if ($result === 'miss' || $result === 'shield_blocked') {
                $room->current_turn = $enemyRole;
            }
        }

        AchievementController::unlock($user, 'pvp_first_match');
        if (count($myShots) === 1 && in_array($result, ['hit', 'sunk'])) {
            AchievementController::unlock($user, 'pvp_first_blood');
        }

        $room->save();

        $shotPayload = [
            'shooter_role'   => $myRole,
            'is_timeout'     => false,
            'x'              => $x,
            'y'              => $y,
            'result'         => $result,
            'display_result' => $displayedResult,
            'ship'           => $hitShipName,
            'next_turn'      => $room->current_turn,
            'status'         => $room->status,
            'winner'         => $room->winner,
        ];

        event(new PvpShotFired($room, $shotPayload));

        return response()->json([
            'status' => 'success',
            'shot'   => $shotPayload,
            'room'   => $room,
        ]);
    }

    public function useSkill(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa xác thực quân tịch!'], 401);
        }

        $roomCode = $request->input('room_code');
        $itemId = $request->input('item_id');
        $targetX = (int) $request->input('target_x', 4);
        $targetY = (int) $request->input('target_y', 4);
        $targetShipName = $request->input('target_ship_name');

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room || $room->status !== 'playing') {
            return response()->json(['error' => 'Trận chiến không tồn tại hoặc chưa bắt đầu!'], 400);
        }

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);
        if (!$isP1 && !$isP2) {
            return response()->json(['error' => 'Bạn không thuộc phòng đấu này!'], 403);
        }

        $myRole = $isP1 ? 'player1' : 'player2';
        $enemyRole = $isP1 ? 'player2' : 'player1';

        if ($room->current_turn !== $myRole) {
            return response()->json(['error' => 'Chưa đến lượt của bạn!'], 400);
        }

        $inv = $user->inventory ?? [];
        if (($inv[$itemId] ?? 0) <= 0) {
            return response()->json(['error' => 'Bạn không còn vật phẩm này trong kho!'], 400);
        }

        $inv[$itemId]--;
        $user->inventory = $inv;
        $user->save();

        $enemyShips = ($isP1 ? $room->p2_ships : $room->p1_ships) ?? [];
        $myShips = ($isP1 ? $room->p1_ships : $room->p2_ships) ?? [];
        $myShots = ($isP1 ? $room->p1_shots : $room->p2_shots) ?? [];
        $enemyShots = ($isP1 ? $room->p2_shots : $room->p1_shots) ?? [];

        $effectData = [
            'item'        => $itemId,
            'user_role'   => $myRole,
            'user_name'   => $user->name,
            'my_msg'      => '',
            'enemy_msg'   => '',
        ];

        $updatedPlayerShips = null;
        $resetDamageCoords = [];

        switch ($itemId) {
            case 'recon_sat':
                $fired = [];
                foreach ($myShots as $s) $fired["{$s['x']},{$s['y']}"] = true;

                $found = null;
                foreach ($enemyShips as $ship) {
                    if (($ship['hits'] ?? 0) < $ship['size']) {
                        foreach ($ship['coordinates'] as $c) {
                            if (!isset($fired["{$c['x']},{$c['y']}"])) {
                                $found = ['x' => $c['x'], 'y' => $c['y'], 'ship' => $ship['name']];
                                break 2;
                            }
                        }
                    }
                }

                AchievementController::unlock($user, 'pvp_recon_master');

                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = $found ? "{$letters[$found['y']]}" . ($found['x'] + 1) : "Không rõ";

                $effectData['type'] = 'recon_sat';
                $effectData['target'] = $found;
                $effectData['my_msg'] = $found 
                    ? "Vệ Tinh phát hiện tàu [{$found['ship']}] của đối phương tại ô [{$coordName}]!" 
                    : "Không còn mục tiêu để trinh sát!";

                $effectData['enemy_msg'] = $found 
                    ? "⚠️ BÁO ĐỘNG ĐỎ: Tàu [{$found['ship']}] của bạn đã bị Vệ Tinh đối phương phát hiện và khóa mục tiêu! Hãy dùng Cơ Động/Tái Cấu Trúc để thoát hiểm!" 
                    : "Vệ tinh đối phương vừa quét qua vùng trời của bạn!";
                break;

            case 'recon_scan':
                $count = 0;
                $enemyCoords = [];
                foreach ($enemyShips as $s) {
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

                AchievementController::unlock($user, 'pvp_recon_master'); // "Vua Trinh Sát PvP"

                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = "{$letters[$targetY]}" . ($targetX + 1);

                $effectData['type'] = 'recon_scan';
                $effectData['cx'] = $targetX;
                $effectData['cy'] = $targetY;
                $effectData['my_msg'] = "Radar quét vùng 3x3 quanh [{$coordName}] phát hiện {$count} ô có tàu!";
                $effectData['enemy_msg'] = "Đối phương vừa quét Radar 3x3 quanh khu vực hải đồ của bạn!";
                break;

            case 'recon_sonar':
                $minDistance = 999;
                foreach ($enemyShips as $s) {
                    if (($s['hits'] ?? 0) < $s['size']) {
                        foreach ($s['coordinates'] as $c) {
                            $dist = abs($c['x'] - $targetX) + abs($c['y'] - $targetY);
                            if ($dist < $minDistance) $minDistance = $dist;
                        }
                    }
                }

                AchievementController::unlock($user, 'pvp_recon_master'); // "Vua Trinh Sát PvP"

                $desc = 'RẤT XA (> 5 ô)';
                if ($minDistance <= 1) $desc = 'RẤT GẦN (Sát cạnh!)';
                elseif ($minDistance <= 2) $desc = 'GẦN (~2 ô)';
                elseif ($minDistance <= 4) $desc = 'XA (3-4 ô)';

                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = "{$letters[$targetY]}" . ($targetX + 1);

                $effectData['type'] = 'recon_sonar';
                $effectData['cx'] = $targetX;
                $effectData['cy'] = $targetY;
                $effectData['my_msg'] = "Sonar xung quanh [{$coordName}]: Tàu địch gần nhất ở cự ly [{$desc}]!";
                $effectData['enemy_msg'] = "Sóng âm Sonar 5x5 của đối phương vừa quét qua vùng biển của bạn!";
                break;

            case 'combat_guided':
                $fired = [];
                foreach ($myShots as $s) $fired["{$s['x']},{$s['y']}"] = true;
                $target = null;

                foreach ($enemyShips as $ship) {
                    if (($ship['hits'] ?? 0) < $ship['size']) {
                        foreach ($ship['coordinates'] as $c) {
                            if (!isset($fired["{$c['x']},{$c['y']}"])) {
                                $target = $c;
                                break 2;
                            }
                        }
                    }
                }

                if ($target) {
                    $effectData['type'] = 'combat_guided';
                    $effectData['my_msg'] = "Đã khai hỏa [TÊN LỬA DẪN ĐƯỜNG] đánh trúng mục tiêu tại tọa độ [".chr(65 + $target['y']).($target['x'] + 1)."]!";
                    $effectData['enemy_msg'] = "CẢNH BÁO: Đối phương đã khai hỏa [TÊN LỬA DẪN ĐƯỜNG] đánh trúng hạm đội của bạn!";
                    event(new PvpSkillUsed($room, $effectData));

                    // Tự động kích hoạt phát bắn
                    $request->merge([
                        'room_code' => $roomCode,
                        'x'         => $target['x'],
                        'y'         => $target['y'],
                        'timeout'   => false
                    ]);
                    return $this->fire($request);
                }

                $effectData['my_msg'] = 'Không tìm thấy mục tiêu khả dụng để phóng tên lửa!';
                $effectData['enemy_msg'] = '';
                break;

            case 'combat_smokescreen':
                $myActive = ($isP1 ? $room->p1_active_skills : $room->p2_active_skills) ?? [];
                $myActive['smoke_turns'] = 5;
                if ($isP1) $room->p1_active_skills = $myActive;
                else $room->p2_active_skills = $myActive;
                $room->save();

                $effectData['type'] = 'combat_smokescreen';
                $effectData['my_msg'] = "Đã thả [MÀN KHÓI NHIỄU LOẠN]! 5 phát bắn tiếp theo của địch vào hạm đội bạn sẽ bị giấu kết quả!";
                $effectData['enemy_msg'] = "Đối phương đã kích hoạt [MÀN KHÓI NHIỄU LOẠN]! Bạn sẽ không thể thấy kết quả trúng/trượt trong 5 phát bắn tiếp theo!";
                break;

            case 'combat_airstrike':
                $room->current_turn = $myRole;
                $room->save();

                $effectData['type'] = 'combat_airstrike';
                $effectData['my_msg'] = "Không Kích thành công! Đối phương bị tước quyền bắn, bạn được bắn tiếp 1 lượt!";
                $effectData['enemy_msg'] = "CẢNH BÁO: Bạn bị Không Kích Phá Rối và MẤT 1 LƯỢT KHAI HỎA!";
                break;

            case 'def_shield':
                $myActive = ($isP1 ? $room->p1_active_skills : $room->p2_active_skills) ?? [];
                $myActive['shield_charges'] = 3;
                if ($isP1) $room->p1_active_skills = $myActive;
                else $room->p2_active_skills = $myActive;
                $room->save();

                $effectData['type'] = 'def_shield';
                $effectData['my_msg'] = "Đã bật [KHIÊN NĂNG LƯỢNG]: Miễn nhiễm 3 phát đạn trúng tiếp theo!";
                $effectData['enemy_msg'] = "CẢNH BÁO: Đối phương đã bật [KHIÊN NĂNG LƯỢNG]!";
                break;

            case 'def_tactical_relocate':
            case 'def_repair_relocate':
                // Tìm tàu cần di dời
                $shipIndex = -1;
                if ($targetShipName) {
                    foreach ($myShips as $i => $s) {
                        if ($s['name'] === $targetShipName) {
                            $shipIndex = $i;
                            break;
                        }
                    }
                }
                
                // Nếu chưa chọn, tự tìm chiếc tàu bị thương (nếu là Tái cấu trúc) hoặc tàu bất kỳ
                if ($shipIndex === -1) {
                    foreach ($myShips as $i => $s) {
                        if ($itemId === 'def_repair_relocate' && ($s['hits'] ?? 0) > 0) {
                            $shipIndex = $i;
                            break;
                        }
                    }
                    if ($shipIndex === -1 && !empty($myShips)) $shipIndex = 0;
                }

                $targetShip = &$myShips[$shipIndex];
                $shipSize = $targetShip['size'];

                // Lưu lại các toạ độ trúng đạn cũ của tàu này để biến thành ô xám
                if ($itemId === 'def_repair_relocate') {
                    $targetShip['hits'] = 0;
                    foreach ($targetShip['coordinates'] as $c) {
                        foreach ($enemyShots as $es) {
                            if ($es['x'] === $c['x'] && $es['y'] === $c['y'] && in_array($es['result'], ['hit', 'sunk'])) {
                                $resetDamageCoords[] = ['x' => $c['x'], 'y' => $c['y']];
                            }
                        }
                    }

                    // Đổi kết quả trong enemyShots thành miss để không còn tính là trúng
                    foreach ($enemyShots as &$es) {
                        foreach ($resetDamageCoords as $rc) {
                            if ($es['x'] === $rc['x'] && $es['y'] === $rc['y']) {
                                $es['result'] = 'miss';
                                $es['ship'] = null;
                            }
                        }
                    }
                    if ($isP1) $room->p2_shots = $enemyShots;
                    else $room->p1_shots = $enemyShots;
                }

                // Thuật toán di dời tàu sang toạ độ an toàn mới
                $firedEnemyCoords = [];
                foreach ($enemyShots as $es) $firedEnemyCoords["{$es['x']},{$es['y']}"] = true;

                $occupiedCoords = [];
                foreach ($myShips as $idx => $s) {
                    if ($idx === $shipIndex) continue;
                    foreach ($s['coordinates'] as $c) $occupiedCoords["{$c['x']},{$c['y']}"] = true;
                }

                $newCoords = null;
                for ($attempt = 0; $attempt < 300; $attempt++) {
                    $isHorizontal = (rand(0, 1) === 1);
                    $startX = rand(0, $isHorizontal ? (10 - $shipSize) : 9);
                    $startY = rand(0, $isHorizontal ? 9 : (10 - $shipSize));

                    $valid = true;
                    $tempCoords = [];
                    for ($step = 0; $step < $shipSize; $step++) {
                        $cx = $isHorizontal ? ($startX + $step) : $startX;
                        $cy = $isHorizontal ? $startY : ($startY + $step);
                        if (isset($occupiedCoords["$cx,$cy"]) || isset($firedEnemyCoords["$cx,$cy"])) {
                            $valid = false;
                            break;
                        }
                        $tempCoords[] = ['x' => $cx, 'y' => $cy];
                    }

                    if ($valid) {
                        $newCoords = $tempCoords;
                        AchievementController::unlock($user, 'pvp_relocate_master');
                        break;
                    }
                }

                if ($newCoords) {
                    $targetShip['coordinates'] = $newCoords;
                    if ($isP1) $room->p1_ships = $myShips;
                    else $room->p2_ships = $myShips;
                    $room->save();

                    $updatedPlayerShips = $myShips;
                    $effectData['type'] = 'def_relocate';
                    $effectData['reset_damage_coords'] = $resetDamageCoords;
                    
                    // Người dùng thấy tàu nào được di chuyển
                    $effectData['my_msg'] = ($itemId === 'def_repair_relocate')
                        ? "Đã kích hoạt [TÁI CẤU TRÚC]: Tàu {$targetShip['name']} hồi phục 100% và bí mật đổi sang vị trí an toàn mới!"
                        : "Đã kích hoạt [CƠ ĐỘNG CHIẾN THUẬT]: Tàu {$targetShip['name']} đã bí mật cơ động đổi hướng!";

                    // Đối thủ chỉ biết có tàu di dời nhưng TUYỆT ĐỐI KHÔNG BIẾT LÀ TÀU NÀO
                    $effectData['enemy_msg'] = ($itemId === 'def_repair_relocate')
                        ? "Đối phương đã kích hoạt [TÁI CẤU TRÚC]! Một chiếc tàu bị thương của địch đã phục hồi và bí mật đổi vị trí!"
                        : "Đối phương đã kích hoạt [CƠ ĐỘNG CHIẾN THUẬT] bí mật chuyển hướng hải hành!";
                } else {
                    $effectData['type'] = 'def_relocate_fail';
                    $effectData['my_msg'] = "Không còn vùng biển an toàn đủ rộng để điều động tàu!";
                    $effectData['enemy_msg'] = "";
                }
                break;

            default:
                $effectData['my_msg'] = "Đã kích hoạt trang bị chiến thuật!";
                $effectData['enemy_msg'] = "Đối phương vừa kích hoạt một trang bị chiến thuật!";
                break;
        }

        event(new PvpSkillUsed($room, $effectData));

        return response()->json([
            'status'               => 'success',
            'inventory'            => $user->inventory,
            'effect'               => $effectData,
            'player_ships'         => $updatedPlayerShips,
            'reset_damage_coords'  => $resetDamageCoords,
        ]);
    }

    /**
     * Đầu hàng hoặc thoát trận đấu PvP
     */
    public function surrender(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $roomCode = $request->input('room_code');
        $reason = $request->input('reason', 'surrender'); // 'surrender' hoặc 'disconnect'

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room || $room->status !== 'playing') {
            return response()->json(['status' => 'ignored']);
        }

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);
        if (!$isP1 && !$isP2) {
            return response()->json(['error' => 'Không thuộc phòng!'], 403);
        }

        $loserRole = $isP1 ? 'player1' : 'player2';
        $winnerRole = $isP1 ? 'player2' : 'player1';

        $room->status = 'finished';
        $room->winner = $winnerRole;
        $room->save();

        $actionMsg = ($reason === 'disconnect')
            ? "Chỉ huy [{$user->name}] đã mất kết nối / rời trận đấu! Xử thua!"
            : "Chỉ huy [{$user->name}] đã chủ động kéo cờ trắng ĐẦU HÀNG!";

        $shotPayload = [
            'shooter_role'   => $loserRole,
            'is_surrender'   => true,
            'reason'         => $reason,
            'status'         => 'finished',
            'winner'         => $winnerRole,
            'msg'            => $actionMsg,
        ];

        event(new PvpShotFired($room, $shotPayload));

        return response()->json([
            'status' => 'success',
            'room'   => $room,
        ]);
    }

    public function rpsChoice(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập!'], 401);

        $roomCode = $request->input('room_code');
        $choice = $request->input('choice'); // 'rock', 'scissors', 'paper'

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room) return response()->json(['error' => 'Không tìm thấy phòng!'], 404);

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);
        if (!$isP1 && !$isP2) return response()->json(['error' => 'Không thuộc phòng này!'], 403);

        $p1Skills = is_array($room->p1_active_skills) ? $room->p1_active_skills : [];
        $p2Skills = is_array($room->p2_active_skills) ? $room->p2_active_skills : [];

        if ($isP1) {
            $p1Skills['rps_choice'] = $choice;
        } else {
            $p2Skills['rps_choice'] = $choice;
        }

        $room->p1_active_skills = $p1Skills;
        $room->p2_active_skills = $p2Skills;
        $room->save();

        $room->refresh();
        $currentP1 = $room->p1_active_skills['rps_choice'] ?? null;
        $currentP2 = $room->p2_active_skills['rps_choice'] ?? null;

        if (!empty($currentP1) && !empty($currentP2)) {
            $c1 = $currentP1;
            $c2 = $currentP2;

            $winConditions = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];

            $outcome = 'tie';
            $winnerRole = null;

            if ($c1 !== $c2) {
                if ($winConditions[$c1] === $c2) {
                    $outcome = 'p1_win';
                    $winnerRole = 'player1';
                } else {
                    $outcome = 'p2_win';
                    $winnerRole = 'player2';
                }
            }

            // Reset lựa chọn và LƯU KẾT QUẢ VÀO ACTIVE_SKILLS ĐỂ POLLING NHẬN DIỆN ĐƯỢC
            $p1Skills = is_array($room->p1_active_skills) ? $room->p1_active_skills : [];
            $p2Skills = is_array($room->p2_active_skills) ? $room->p2_active_skills : [];
            $p1Skills['rps_choice'] = null;
            $p2Skills['rps_choice'] = null;

            $rpsPayload = [
                'type'        => 'result',
                'p1_choice'   => $c1,
                'p2_choice'   => $c2,
                'outcome'     => $outcome,
                'winner_role' => $winnerRole,
                'timestamp'   => microtime(true), // Đánh dấu thời gian tránh xử lý trùng
            ];

            $p1Skills['last_rps_result'] = $rpsPayload;
            $p2Skills['last_rps_result'] = $rpsPayload;
            
            $room->p1_active_skills = $p1Skills;
            $room->p2_active_skills = $p2Skills;
            $room->save();

            try {
                event(new \App\Events\PvpRpsEvent($room, $rpsPayload));
            } catch (\Exception $e) {}

            return response()->json(['status' => 'both_picked', 'data' => $rpsPayload]);
        }

        return response()->json(['status' => 'waiting_other']);
    }

    public function rpsDecideTurn(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập!'], 401);

        $roomCode = $request->input('room_code');
        $choice = $request->input('choice'); // 'first' hoặc 'second'

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room) return response()->json(['error' => 'Không tìm thấy phòng!'], 404);

        $isP1 = ($room->player1_id === $user->id);
        $myRole = $isP1 ? 'player1' : 'player2';
        $enemyRole = $isP1 ? 'player2' : 'player1';

        // Lượt đi đầu tiên
        $starterRole = ($choice === 'first') ? $myRole : $enemyRole;

        $room->status = 'playing';
        $room->current_turn = $starterRole;
        $room->save();

        $rpsPayload = [
            'type'         => 'turn_decided',
            'starter_role' => $starterRole,
        ];

        event(new \App\Events\PvpRpsEvent($room, $rpsPayload));

        return response()->json(['status' => 'success', 'starter' => $starterRole]);
    }

    public function getRoomStatus(Request $request): JsonResponse
    {
        $roomCode = strtoupper(trim($request->input('room_code')));
        $room = Room::where('room_code', $roomCode)->first();
        if (!$room) {
            return response()->json(['error' => 'Phòng không tồn tại'], 404);
        }
        return response()->json(['room' => $room]);
    }

    public function syncRoomState(Request $request): JsonResponse
    {
        $roomCode = strtoupper(trim($request->input('room_code')));
        $room = Room::where('room_code', $roomCode)->first();

        if (!$room) {
            return response()->json(['error' => 'Phòng không tồn tại'], 404);
        }

        /** @var User|null $user */
        $user = Auth::user();
        
        // Xác định chính xác role của người đang gọi API này
        $myRole = 'spectator';
        if ($user) {
            if ($room->player1_id === $user->id) {
                $myRole = 'player1';
            } elseif ($room->player2_id === $user->id) {
                $myRole = 'player2';
            }
        }

        $p1Skills = is_array($room->p1_active_skills) ? $room->p1_active_skills : [];

        return response()->json([
            'status'       => 'success',
            'room'         => $room,
            'my_role'      => $myRole, // Luôn chuẩn xác 100% theo session user
            'current_turn' => $room->current_turn,
            'game_status'  => $room->status,
            'winner'       => $room->winner,
            'player2_id'   => $room->player2_id,
            'rps_result'   => $p1Skills['last_rps_result'] ?? null,
            'p1_shots'     => $room->p1_shots ?? [],
            'p2_shots'     => $room->p2_shots ?? [],
            'p1_ready'     => (bool) $room->p1_ready,
            'p2_ready'     => (bool) $room->p2_ready,
        ]);
    }
}