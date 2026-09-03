<?php

namespace App\Services;

class BotService
{
    public const FLEET = [
        ['name' => 'Carrier', 'size' => 5],
        ['name' => 'Battleship', 'size' => 4],
        ['name' => 'Cruiser', 'size' => 3],
        ['name' => 'Submarine', 'size' => 3],
        ['name' => 'Destroyer', 'size' => 2],
    ];

    /**
     * Rải ngẫu nhiên 5 tàu vào ma trận 10x10
     */
    public function placeFleetRandomly(): array
    {
        $ships = [];
        $occupied = [];

        foreach (self::FLEET as $shipSpec) {
            $placed = false;
            while (!$placed) {
                $horizontal = (bool)rand(0, 1);
                $x = rand(0, 9);
                $y = rand(0, 9);

                $coordinates = [];
                $valid = true;

                for ($i = 0; $i < $shipSpec['size']; $i++) {
                    $currX = $horizontal ? $x + $i : $x;
                    $currY = $horizontal ? $y : $y + $i;

                    if ($currX > 9 || $currY > 9 || isset($occupied["$currX,$currY"])) {
                        $valid = false;
                        break;
                    }
                    $coordinates[] = ['x' => $currX, 'y' => $currY];
                }

                if ($valid) {
                    foreach ($coordinates as $coord) {
                        $occupied["{$coord['x']},{$coord['y']}"] = true;
                    }
                    $ships[] = [
                        'name' => $shipSpec['name'],
                        'size' => $shipSpec['size'],
                        'coordinates' => $coordinates,
                        'hits' => 0
                    ];
                    $placed = true;
                }
            }
        }
        return $ships;
    }

    /**
     * Quyết định tọa độ bắn tiếp theo của Bot dựa trên cấp độ
     */
    public function makeMove(string $difficulty, array $shots, ?array $memory, array $playerShips): array
    {
        $allFired = [];
        foreach ($shots as $shot) {
            $allFired["{$shot['x']},{$shot['y']}"] = $shot['result'];
        }

        return match ($difficulty) {
            'medium' => $this->mediumMove($allFired, $memory),
            'hard' => $this->hardMove($allFired, $memory),
            'nightmare' => $this->nightmareMove($allFired, $playerShips),
            default => $this->easyMove($allFired),
        };
    }

    // DỄ: Bắn ngẫu nhiên vào các ô chưa bắn
    protected function easyMove(array $fired): array
    {
        do {
            $x = rand(0, 9);
            $y = rand(0, 9);
        } while (isset($fired["$x,$y"]));

        return ['x' => $x, 'y' => $y, 'memory' => null];
    }

    // TRUNG BÌNH: Hunt / Target (nếu bắn trúng, bắn các ô xung quanh)
    protected function mediumMove(array $fired, ?array $memory): array
    {
        // Nếu trong memory còn ô mục tiêu lân cận cần kiểm tra
        if (!empty($memory['targets'])) {
            $target = array_shift($memory['targets']);
            return ['x' => $target['x'], 'y' => $target['y'], 'memory' => $memory];
        }

        // Nếu không có mục tiêu, bắn ngẫu nhiên
        return $this->easyMove($fired);
    }

    // KHÓ: Áp dụng Parity (bàn cờ caro) để giảm 50% số lượt dò
    protected function hardMove(array $fired, ?array $memory): array
    {
        if (!empty($memory['targets'])) {
            $target = array_shift($memory['targets']);
            return ['x' => $target['x'], 'y' => $target['y'], 'memory' => $memory];
        }

        $candidates = [];
        for ($x = 0; $x < 10; $x++) {
            for ($y = 0; $y < 10; $y++) {
                // Chỉ chọn ô chẵn/lẻ xen kẽ (x + y) % 2 == 0
                if (($x + $y) % 2 === 0 && !isset($fired["$x,$y"])) {
                    $candidates[] = ['x' => $x, 'y' => $y];
                }
            }
        }

        if (empty($candidates)) {
            return $this->easyMove($fired);
        }

        $choice = $candidates[array_rand($candidates)];
        return ['x' => $choice['x'], 'y' => $choice['y'], 'memory' => $memory];
    }

    // CỰC KHÓ (NIGHTMARE): Tính toán bản đồ xác suất dựa trên các vị trí tàu còn lại
    protected function nightmareMove(array $fired, array $playerShips): array
    {
        // Lọc các tàu chưa bị chìm hoàn toàn
        $aliveShips = array_filter($playerShips, fn($s) => $s['hits'] < $s['size']);

        $heatmap = array_fill(0, 10, array_fill(0, 10, 0));

        foreach ($aliveShips as $ship) {
            $len = $ship['size'];

            // Quét ngang & dọc để tính điểm xác suất
            for ($x = 0; $x < 10; $x++) {
                for ($y = 0; $y < 10; $y++) {
                    // Ngang
                    if ($x + $len <= 10) {
                        $valid = true;
                        for ($i = 0; $i < $len; $i++) {
                            if (isset($fired[($x + $i) . ",$y"]) && $fired[($x + $i) . ",$y"] === 'miss') {
                                $valid = false;
                                break;
                            }
                        }
                        if ($valid) {
                            for ($i = 0; $i < $len; $i++) {
                                if (!isset($fired[($x + $i) . ",$y"])) {
                                    $heatmap[$x + $i][$y]++;
                                }
                            }
                        }
                    }

                    // Dọc
                    if ($y + $len <= 10) {
                        $valid = true;
                        for ($i = 0; $i < $len; $i++) {
                            if (isset($fired["$x," . ($y + $i)]) && $fired["$x," . ($y + $i)] === 'miss') {
                                $valid = false;
                                break;
                            }
                        }
                        if ($valid) {
                            for ($i = 0; $i < $len; $i++) {
                                if (!isset($fired["$x," . ($y + $i)])) {
                                    $heatmap[$x][$y + $i]++;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Tìm ô có xác suất cao nhất
        $bestScore = -1;
        $bestCoords = [];
        for ($x = 0; $x < 10; $x++) {
            for ($y = 0; $y < 10; $y++) {
                if (!isset($fired["$x,$y"])) {
                    if ($heatmap[$x][$y] > $bestScore) {
                        $bestScore = $heatmap[$x][$y];
                        $bestCoords = [['x' => $x, 'y' => $y]];
                    } elseif ($heatmap[$x][$y] === $bestScore) {
                        $bestCoords[] = ['x' => $x, 'y' => $y];
                    }
                }
            }
        }

        $choice = !empty($bestCoords) ? $bestCoords[array_rand($bestCoords)] : ['x' => rand(0, 9), 'y' => rand(0, 9)];
        return ['x' => $choice['x'], 'y' => $choice['y'], 'memory' => null];
    }
}