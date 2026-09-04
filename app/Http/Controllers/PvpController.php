<?php

namespace App\Http\Controllers;

use App\Events\PvpGameStarted;
use App\Events\PvpShotFired;
use App\Events\PvpSkillUsed;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PvpController extends Controller
{
    /**
     * Tạo phòng đấu PvP mới
     */
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
            'message'   => 'Đã tạo phòng chờ thành công! Hãy gửi mã phòng cho đối thủ.',
        ]);
    }

    /**
     * Tham gia vào phòng đấu bằng mã phòng
     */
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

        if ($room->status !== 'waiting') {
            return response()->json(['error' => 'Phòng này đã bắt đầu trận chiến hoặc đã đóng!'], 400);
        }

        if ($room->player1_id === $user->id) {
            return response()->json(['status' => 'success', 'room' => $room, 'role' => 'player1']);
        }

        if ($room->player2_id && $room->player2_id !== $user->id) {
            return response()->json(['error' => 'Phòng đấu đã đủ 2 chỉ huy!'], 400);
        }

        $room->player2_id = $user->id;
        $room->status = 'setup'; 
        $room->save();

        // --- CỰC KỲ QUAN TRỌNG: PHÁT SỰ KIỆN QUA WEBSOCKET ---
        broadcast(new \App\Events\PlayerJoinedRoom($room))->toOthers();

        return response()->json([
            'status' => 'success',
            'room'   => $room,
            'role'   => 'player2',
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

        // Nếu cả 2 đều đã sẵn sàng -> Bắt đầu trận chiến!
        if ($room->p1_ready && $room->p2_ready) {
            $room->status = 'playing';
            $room->current_turn = 'player1'; // Player 1 đánh trước
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
        $x = (int) $request->input('x');
        $y = (int) $request->input('y');

        $room = Room::where('room_code', $roomCode)->first();
        if (!$room || $room->status !== 'playing') {
            return response()->json(['error' => 'Trận đấu không tồn tại hoặc chưa bắt đầu!'], 400);
        }

        $isP1 = ($room->player1_id === $user->id);
        $isP2 = ($room->player2_id === $user->id);

        if (!$isP1 && !$isP2) {
            return response()->json(['error' => 'Bạn không thuộc phòng này!'], 403);
        }

        $myRole = $isP1 ? 'player1' : 'player2';
        if ($room->current_turn !== $myRole) {
            return response()->json(['error' => 'Chưa đến lượt của bạn!'], 400);
        }

        $myShots = ($isP1 ? $room->p1_shots : $room->p2_shots) ?? [];
        foreach ($myShots as $shot) {
            if ($shot['x'] === $x && $shot['y'] === $y) {
                return response()->json(['error' => 'Tọa độ này đã bắn rồi!'], 422);
            }
        }

        // Tàu của đối thủ
        $enemyShips = ($isP1 ? $room->p2_ships : $room->p1_ships) ?? [];
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

        $myShots[] = ['x' => $x, 'y' => $y, 'result' => $result, 'ship' => $hitShipName];

        if ($isP1) {
            $room->p1_shots = $myShots;
            $room->p2_ships = $enemyShips;
        } else {
            $room->p2_shots = $myShots;
            $room->p1_ships = $enemyShips;
        }

        // Kiểm tra đối thủ đã chìm hết tàu chưa
        $enemyDestroyed = true;
        foreach ($enemyShips as $s) {
            if (($s['hits'] ?? 0) < $s['size']) {
                $enemyDestroyed = false;
                break;
            }
        }

        if ($enemyDestroyed) {
            $room->status = 'finished';
            $room->winner = $myRole;
        } else {
            // Đổi lượt nếu bắn trượt, bắn trúng được bắn tiếp
            if ($result === 'miss') {
                $room->current_turn = $isP1 ? 'player2' : 'player1';
            }
        }

        $room->save();

        $shotPayload = [
            'shooter_role' => $myRole,
            'x'            => $x,
            'y'            => $y,
            'result'       => $result,
            'ship'         => $hitShipName,
            'next_turn'    => $room->current_turn,
            'status'       => $room->status,
            'winner'       => $room->winner,
        ];

        event(new PvpShotFired($room, $shotPayload));

        return response()->json([
            'status'   => 'success',
            'shot'     => $shotPayload,
            'room'     => $room,
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
        if ($room->current_turn !== $myRole) {
            return response()->json(['error' => 'Chưa đến lượt của bạn!'], 400);
        }

        $inv = $user->inventory ?? [];
        if (($inv[$itemId] ?? 0) <= 0) {
            return response()->json(['error' => 'Bạn không còn vật phẩm này trong kho!'], 400);
        }

        // Trừ 1 vật phẩm
        $inv[$itemId]--;
        $user->inventory = $inv;
        $user->save();

        $enemyShips = ($isP1 ? $room->p2_ships : $room->p1_ships) ?? [];
        $myShips = ($isP1 ? $room->p1_ships : $room->p2_ships) ?? [];
        $myShots = ($isP1 ? $room->p1_shots : $room->p2_shots) ?? [];

        $effectData = [
            'item'         => $itemId,
            'user_role'    => $myRole,
            'user_name'    => $user->name,
        ];

        switch ($itemId) {
            case 'recon_sat':
                // Tìm 1 ô tàu địch chưa bắn
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
                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = $found ? "{$letters[$found['y']]}" . ($found['x'] + 1) : "Không rõ";

                $effectData['type'] = 'recon_sat';
                $effectData['target'] = $found;
                $effectData['msg'] = $found 
                    ? "Vệ Tinh phát hiện tàu [{$found['ship']}] của đối thủ tại ô [{$coordName}]!" 
                    : "Không còn tọa độ tàu nào khả dụng để quét!";
                break;

            case 'recon_scan':
                // Radar vùng 3x3 quanh tâm targetX, targetY
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
                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = "{$letters[$targetY]}" . ($targetX + 1);

                $effectData['type'] = 'recon_scan';
                $effectData['cx'] = $targetX;
                $effectData['cy'] = $targetY;
                $effectData['count'] = $count;
                $effectData['msg'] = "Radar quét 3x3 quanh tâm [{$coordName}] phát hiện {$count} ô có tàu đối phương!";
                break;

            case 'recon_sonar':
                // Sonar cảm biến cự ly 5x5
                $minDistance = 999;
                foreach ($enemyShips as $s) {
                    if (($s['hits'] ?? 0) < $s['size']) {
                        foreach ($s['coordinates'] as $c) {
                            $dist = abs($c['x'] - $targetX) + abs($c['y'] - $targetY);
                            if ($dist < $minDistance) $minDistance = $dist;
                        }
                    }
                }
                $desc = 'RẤT XA (> 5 ô)';
                if ($minDistance <= 1) $desc = 'RẤT GẦN (Sát cạnh!)';
                elseif ($minDistance <= 2) $desc = 'GẦN (~2 ô)';
                elseif ($minDistance <= 4) $desc = 'XA (3-4 ô)';

                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $coordName = "{$letters[$targetY]}" . ($targetX + 1);

                $effectData['type'] = 'recon_sonar';
                $effectData['cx'] = $targetX;
                $effectData['cy'] = $targetY;
                $effectData['msg'] = "Sonar xung quanh [{$coordName}]: Tàu địch gần nhất đang ở cự ly [{$desc}]!";
                break;

            case 'combat_guided':
                // Tên lửa dẫn đường: Tự động bắn trúng 1 ô tàu địch còn nguyên
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
                    $fakeReq = new Request([
                        'room_code' => $roomCode,
                        'x'         => $target['x'],
                        'y'         => $target['y'],
                    ]);
                    return $this->fire($fakeReq);
                }
                $effectData['msg'] = 'Không còn mục tiêu để phóng tên lửa!';
                break;

            case 'def_shield':
                // Kích hoạt khiên chắn
                $myActive = ($isP1 ? $room->p1_active_skills : $room->p2_active_skills) ?? [];
                $myActive['shield_charges'] = 3;
                if ($isP1) $room->p1_active_skills = $myActive;
                else $room->p2_active_skills = $myActive;
                $room->save();

                $effectData['type'] = 'def_shield';
                $effectData['msg'] = "Đã kích hoạt Khiên Năng Lượng: Bảo vệ hạm đội khỏi 3 phát bắn tiếp theo!";
                break;

            default:
                $effectData['msg'] = "Đã kích hoạt trang bị chiến thuật!";
                break;
        }

        // Phát tín hiệu qua Reverb cho cả 2 máy cùng thấy hiệu ứng
        event(new PvpSkillUsed($room, $effectData));

        return response()->json([
            'status'    => 'success',
            'inventory' => $user->inventory,
            'effect'    => $effectData,
        ]);
    }
}