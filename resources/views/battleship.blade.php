<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Battleship War - Naval Tactical Command</title>
    <!-- Google Fonts: Chakra Petch (Gaming / Military) & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Chakra Petch', sans-serif;
            background-color: #0b0f19;
            background-image: 
                radial-gradient(ellipse at 50% 0%, rgba(6, 182, 212, 0.15), transparent 60%),
                radial-gradient(circle at 100% 100%, rgba(225, 29, 72, 0.08), transparent 40%);
        }
        .font-mono-tactical {
            font-family: 'JetBrains Mono', monospace;
        }
        .grid-board {
            display: grid;
            grid-template-columns: 28px repeat(10, 36px);
            grid-template-rows: 28px repeat(10, 36px);
            gap: 3px;
        }
        .cell {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 700;
            user-select: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .header-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            user-select: none;
        }
        /* Custom Select HUD Theme */
        .select-tactical {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2306b6d4'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
        }
        /* Hiệu ứng radar quét nhẹ trên mặt biển đối phương */
        .radar-scan {
            position: relative;
            overflow: hidden;
        }
        .radar-scan::before {
            content: "";
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: linear-gradient(rgba(244, 63, 94, 0.04) 50%, rgba(244, 63, 94, 0.12) 51%, transparent 55%);
            animation: radarSweep 6s linear infinite;
            pointer-events: none;
        }
        @keyframes radarSweep {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Custom Scrollbar hợp theme Tactical HUD */
        #battleLog::-webkit-scrollbar {
            width: 6px;
        }
        #battleLog::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 9999px;
        }
        #battleLog::-webkit-scrollbar-thumb {
            background: #0e7490; /* cyan-700 */
            border-radius: 9999px;
            box-shadow: 0 0 6px rgba(6, 182, 212, 0.4);
        }
        #battleLog::-webkit-scrollbar-thumb:hover {
            background: #06b6d4; /* cyan-500 sáng hơn khi hover */
        }
        /* Hỗ trợ chuẩn Firefox */
        #battleLog {
            scrollbar-width: thin;
            scrollbar-color: #0e7490 rgba(15, 23, 42, 0.6);
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen p-6 antialiased selection:bg-cyan-500 selection:text-black">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <header class="flex flex-wrap justify-between items-center mb-6 pb-4 border-b border-cyan-900/40">
            <div>
                <div class="flex items-center gap-3">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-cyan-400 animate-ping"></span>
                    <h1 class="text-3xl font-extrabold tracking-wider bg-gradient-to-r from-cyan-400 via-teal-300 to-sky-500 bg-clip-text text-transparent uppercase">
                        Battleship Tactical War
                    </h1>
                </div>
                <p id="gameStatusText" class="text-slate-400 mt-1 text-sm tracking-wide">
                    HỆ THỐNG DÀN TRẬN: Hãy đặt đủ 5 tàu chiến lên bàn cờ để kích hoạt radar.
                </p>
            </div>
            
            <div class="flex items-center gap-3 mt-3 sm:mt-0">
                <!-- Dropdown thiết kế theo chuẩn HUD Cyberpunk -->
                <div class="relative">
                    <select id="difficultySelect" class="select-tactical font-semibold bg-slate-900/90 text-cyan-400 border border-cyan-500/40 hover:border-cyan-400 rounded-md pl-4 pr-9 py-2 text-sm shadow-[0_0_15px_rgba(6,182,212,0.15)] focus:outline-none focus:ring-1 focus:ring-cyan-400 cursor-pointer transition">
                        <option value="random" class="bg-slate-900 text-amber-300">🎲 Ngẫu nhiên (Bí ẩn)</option>
                        <option value="easy" class="bg-slate-900 text-slate-200">Dễ (Ngẫu nhiên)</option>
                        <option value="medium" class="bg-slate-900 text-slate-200">Trung bình (Hunt / Target)</option>
                        <option value="hard" class="bg-slate-900 text-slate-200">Khó (Parity Checkerboard)</option>
                        <option value="nightmare" class="bg-slate-900 text-rose-400">Cực khó (Bản đồ xác suất)</option>
                    </select>
                </div>
                <button onclick="resetSetup()" class="bg-slate-800/80 hover:bg-slate-750 hover:text-cyan-300 text-slate-300 border border-slate-700 px-4 py-2 rounded-md text-sm font-semibold tracking-wider uppercase transition shadow">
                    Xếp Lại
                </button>
            </div>
        </header>

        <!-- Thanh công cụ điều khiển Dàn trận -->
        <div id="placementControls" class="bg-slate-900/60 backdrop-blur-md p-4 rounded-xl border border-cyan-900/50 mb-6 flex flex-wrap items-center justify-between gap-4 shadow-[0_4px_20px_rgba(0,0,0,0.5)]">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs uppercase tracking-widest font-semibold text-slate-400">Chọn tàu:</span>
                <div id="shipButtons" class="flex gap-2 flex-wrap"></div>
            </div>
            <div class="flex items-center gap-3">
                <button id="btnRotate" onclick="toggleOrientation()" class="bg-indigo-600/80 hover:bg-indigo-600 text-indigo-100 border border-indigo-400/30 px-3.5 py-1.5 rounded-md text-xs uppercase tracking-wider font-bold transition shadow-[0_0_10px_rgba(99,102,241,0.2)]">
                    Hướng: <span id="orientationText" class="text-white font-extrabold underline decoration-indigo-300">Ngang</span> (Phím R)
                </button>
                <button id="btnStartWar" onclick="confirmDeployment()" disabled class="bg-emerald-600 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-emerald-500 text-white font-bold px-5 py-1.5 rounded-md text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                    Vào Trận (0/5)
                </button>
            </div>
        </div>

        <main class="flex flex-wrap justify-center gap-10 mt-2">
            <!-- Bàn cờ người chơi -->
            <div class="flex flex-col items-center">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <h2 class="text-base font-bold tracking-wider text-cyan-300 uppercase">Hạm Đội Của Bạn</h2>
                </div>
                <div id="playerGrid" class="grid-board bg-slate-900/80 p-2.5 rounded-xl border-2 border-cyan-900/70 shadow-[0_0_20px_rgba(6,182,212,0.1)]"></div>
            </div>

            <!-- Bàn cờ Bot -->
            <div class="flex flex-col items-center">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <h2 class="text-base font-bold tracking-wider text-rose-400 uppercase">Vùng Biển Đối Phương</h2>
                </div>
                <div id="botGrid" class="grid-board radar-scan bg-slate-900/80 p-2.5 rounded-xl border-2 border-rose-950/70 opacity-40 pointer-events-none transition-all shadow-[0_0_20px_rgba(244,63,94,0.08)]"></div>
            </div>
        </main>

        <!-- Bảng xếp hạng Top 5 Của Cấp Máy Đang Chọn -->
        <div class="mt-8 bg-slate-900/80 backdrop-blur-md p-5 rounded-xl border border-cyan-900/60 max-w-4xl mx-auto shadow-[0_0_25px_rgba(6,182,212,0.15)]">
            <div class="flex justify-between items-center mb-4 border-b border-cyan-900/50 pb-2">
                <div class="flex items-center gap-2">
                    <span class="text-amber-400 text-lg">🏆</span>
                    <h3 class="text-sm font-bold tracking-wider uppercase text-cyan-300">
                        Bảng Vinh Danh Top 5 - Bot Cấp Độ: <span id="lbDifficultyTitle" class="text-amber-400">TRUNG BÌNH</span>
                    </h3>
                </div>
                <span class="text-xs text-slate-500 font-mono-tactical">TOP COMMANDERS</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono-tactical">
                    <thead class="text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-2 px-3">HẠNG</th>
                            <th class="py-2 px-3">CHỈ HUY</th>
                            <th class="py-2 px-3 text-cyan-400">ĐIỂM SỐ</th>
                            <th class="py-2 px-3">THỜI GIAN</th>
                            <th class="py-2 px-3">CHÍNH XÁC</th>
                            <th class="py-2 px-3">BẢO TOÀN</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboardBody" class="divide-y divide-slate-800/60 text-slate-300">
                        <tr><td colspan="6" class="py-3 text-center text-slate-500 italic">Đang tải dữ liệu tác chiến...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Vinh Danh Thắng Trận (Victory Modal) -->
        <div id="victoryModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border-2 border-amber-400/80 p-6 rounded-2xl max-w-md w-full text-center shadow-[0_0_40px_rgba(251,191,36,0.3)]">
                <span class="text-4xl">🎖️</span>
                <h2 class="text-2xl font-black text-amber-400 uppercase tracking-widest mt-2">CHIẾN THẮNG HUY HOÀNG</h2>
                <p class="text-xs text-slate-400 mt-1">Toàn bộ hạm đội đối phương đã bị tiêu diệt</p>

                <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 my-5 grid grid-cols-2 gap-3 text-left font-mono-tactical text-xs">
                    <div>
                        <span class="text-slate-500 block">ĐIỂM TỔNG CỘNG:</span>
                        <span id="resScore" class="text-lg font-bold text-amber-300">0</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">THỜI GIAN ĐÁNH BẠI:</span>
                        <span id="resTime" class="text-base font-semibold text-cyan-400">0s</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">ĐỘ CHÍNH XÁC:</span>
                        <span id="resAccuracy" class="text-sm font-semibold text-emerald-400">0%</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">HẠM ĐỘI SỐNG SÓT:</span>
                        <span id="resFleetHp" class="text-sm font-semibold text-sky-400">0%</span>
                    </div>
                </div>

                <div class="mb-4 text-left">
                    <label class="block text-xs font-semibold text-slate-400 mb-1 tracking-wider uppercase">Nhập danh hiệu Chỉ Huy:</label>
                    <input id="commanderName" type="text" maxlength="15" placeholder="Commander X" class="w-full bg-slate-950 border border-cyan-500/50 rounded-lg px-3 py-2 text-sm text-cyan-300 focus:outline-none focus:border-cyan-400 font-mono-tactical">
                </div>

                <button onclick="submitScore()" class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-black tracking-widest uppercase py-2.5 rounded-lg transition shadow-[0_0_15px_rgba(245,158,11,0.4)]">
                    Ghi Danh Vào Bảng Rank
                </button>
            </div>
        </div>

        <!-- Nhật ký tác chiến -->
        <div class="mt-8 bg-slate-900/70 backdrop-blur-md p-4 rounded-xl border border-slate-800 max-w-2xl mx-auto h-32 overflow-y-auto font-mono-tactical text-xs shadow-inner" id="battleLog">
            <div class="text-slate-500 italic">> Hệ thống chỉ huy tác chiến đã sẵn sàng...</div>
        </div>
    </div>

    <script>
        const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        const SHIPS_DATA = [
            { name: 'Carrier', size: 5 },
            { name: 'Battleship', size: 4 },
            { name: 'Cruiser', size: 3 },
            { name: 'Submarine', size: 3 },
            { name: 'Destroyer', size: 2 }
        ];

        let isHorizontal = true;
        let selectedShipIndex = 0;
        let placedShips = []; 
        let phase = 'setup';
        let currentGameId = null;

        function toCoordName(x, y) {
            return `${LETTERS[y]}${x + 1}`;
        }

        function log(msg, color = 'text-slate-300') {
            const logBox = document.getElementById('battleLog');
            const item = document.createElement('div');
            item.className = `${color} mb-1 tracking-wide`;
            item.innerHTML = `> ${msg}`;
            logBox.prepend(item);
        }

        function toggleOrientation() {
            isHorizontal = !isHorizontal;
            document.getElementById('orientationText').innerText = isHorizontal ? 'Ngang' : 'Dọc';
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'r' || e.key === 'R') toggleOrientation();
        });

        function renderShipButtons() {
            const container = document.getElementById('shipButtons');
            container.innerHTML = '';
            SHIPS_DATA.forEach((s, idx) => {
                const isPlaced = placedShips.some(p => p.name === s.name);
                const btn = document.createElement('button');
                btn.className = `px-3 py-1 text-xs rounded-md font-semibold tracking-wider uppercase border transition ${
                    isPlaced ? 'bg-slate-800/40 text-slate-600 border-slate-800/60 line-through cursor-not-allowed' :
                    selectedShipIndex === idx 
                        ? 'bg-cyan-500 text-slate-950 border-cyan-300 shadow-[0_0_12px_rgba(6,182,212,0.4)]' 
                        : 'bg-slate-800 hover:bg-slate-750 text-slate-300 border-slate-700 hover:border-slate-600'
                }`;
                btn.innerText = `${s.name} [${s.size}]`;
                if (!isPlaced) {
                    btn.onclick = () => { selectedShipIndex = idx; renderShipButtons(); };
                }
                container.appendChild(btn);
            });

            const btnStart = document.getElementById('btnStartWar');
            btnStart.innerText = `Vào Trận (${placedShips.length}/5)`;
            btnStart.disabled = placedShips.length < 5;
        }

        function buildGridWithHeaders(containerId, isBot = false) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';

            const corner = document.createElement('div');
            corner.className = 'header-cell font-mono-tactical text-slate-600';
            corner.innerText = '#';
            container.appendChild(corner);

            for (let x = 1; x <= 10; x++) {
                const colHead = document.createElement('div');
                colHead.className = 'header-cell font-mono-tactical text-slate-400';
                colHead.innerText = x;
                container.appendChild(colHead);
            }

            for (let y = 0; y < 10; y++) {
                const rowHead = document.createElement('div');
                rowHead.className = `header-cell font-mono-tactical ${isBot ? 'text-rose-400' : 'text-cyan-400'}`;
                rowHead.innerText = LETTERS[y];
                container.appendChild(rowHead);

                for (let x = 0; x < 10; x++) {
                    const cell = document.createElement('div');
                    const prefix = isBot ? 'b' : 'p';
                    cell.id = `${prefix}-${x}-${y}`;
                    cell.className = 'cell bg-slate-800/40 hover:bg-slate-800/80 border border-slate-800 rounded-sm';

                    if (!isBot) {
                        cell.classList.add('cursor-pointer', 'hover:border-cyan-500/50');
                        cell.onclick = () => handlePlayerCellClick(x, y);
                    } else {
                        cell.classList.add('hover:border-rose-500/60');
                        cell.onclick = () => fireAt(x, y);
                    }
                    container.appendChild(cell);
                }
            }
        }

        function renderEmptyBoards() {
            buildGridWithHeaders('playerGrid', false);
            buildGridWithHeaders('botGrid', true);
        }

        function handlePlayerCellClick(x, y) {
            if (phase !== 'setup') return;

            const shipSpec = SHIPS_DATA[selectedShipIndex];
            if (placedShips.some(p => p.name === shipSpec.name)) return;

            const coords = [];
            for (let i = 0; i < shipSpec.size; i++) {
                const currX = isHorizontal ? x + i : x;
                const currY = isHorizontal ? y : y + i;

                if (currX > 9 || currY > 9) {
                    log(`CẢNH BÁO: Tàu ${shipSpec.name} vượt quá phạm vi hải đồ!`, 'text-amber-400');
                    return;
                }

                for (let placed of placedShips) {
                    if (placed.coordinates.some(c => c.x === currX && c.y === currY)) {
                        log(`CẢNH BÁO: Va chạm tọa độ với tàu đã triển khai!`, 'text-amber-400');
                        return;
                    }
                }
                coords.push({ x: currX, y: currY });
            }

            placedShips.push({
                name: shipSpec.name,
                size: shipSpec.size,
                coordinates: coords,
                hits: 0
            });

            coords.forEach(c => {
                const el = document.getElementById(`p-${c.x}-${c.y}`);
                el.className = 'cell bg-cyan-600/90 border border-cyan-400 text-white rounded-sm shadow-[0_0_8px_rgba(6,182,212,0.3)]';
            });

            log(`Triển khai thành công: ${shipSpec.name} tại [${toCoordName(x, y)}]`, 'text-cyan-300');

            const nextIdx = SHIPS_DATA.findIndex(s => !placedShips.some(p => p.name === s.name));
            if (nextIdx !== -1) selectedShipIndex = nextIdx;

            renderShipButtons();
        }

        function resetSetup() {
            placedShips = [];
            selectedShipIndex = 0;
            phase = 'setup';
            currentGameId = null;
            const botGrid = document.getElementById('botGrid');
            botGrid.classList.add('opacity-40', 'pointer-events-none');
            botGrid.classList.remove('border-rose-600', 'shadow-[0_0_20px_rgba(244,63,94,0.3)]');
            document.getElementById('placementControls').classList.remove('hidden');
            document.getElementById('gameStatusText').innerText = "HỆ THỐNG DÀN TRẬN: Hãy đặt đủ 5 tàu chiến lên bàn cờ để kích hoạt radar.";
            renderEmptyBoards();
            renderShipButtons();
            log('Đã cài đặt lại toàn bộ hải đồ tác chiến.', 'text-slate-500');
        }

        async function confirmDeployment() {
            if (placedShips.length < 5) return;

            let selectedDifficulty = document.getElementById('difficultySelect').value;
            let actualDifficulty = selectedDifficulty;

            // Xử lý chế độ Random độ khó
            if (selectedDifficulty === 'random') {
                const levels = ['easy', 'medium', 'hard', 'nightmare'];
                actualDifficulty = levels[Math.floor(Math.random() * levels.length)];
            }

            const res = await fetch('/api/games', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    difficulty: actualDifficulty, 
                    mode: 'pve', 
                    player_ships: placedShips 
                })
            });

            const data = await res.json();
            currentGameId = data.game_id;
            phase = 'playing';

            document.getElementById('placementControls').classList.add('hidden');
            const botGrid = document.getElementById('botGrid');
            botGrid.classList.remove('opacity-40', 'pointer-events-none');
            botGrid.classList.add('border-rose-600/70', 'shadow-[0_0_25px_rgba(244,63,94,0.2)]');
            
            document.getElementById('gameStatusText').innerText = "HỆ THỐNG RADAR KÍCH HOẠT: Chọn tọa độ trên vùng biển đối phương để khai hỏa!";
            
            if (selectedDifficulty === 'random') {
                log(`Đã kích hoạt chế độ NGẪU NHIÊN BÍ MẬT! Đối thủ của bạn mang cấp độ chưa rõ.`, 'text-amber-400 font-bold');
            } else {
                log(`Hải đồ thiết lập hoàn tất. Cấp độ tác chiến: ${actualDifficulty.toUpperCase()}`, 'text-emerald-400');
            }
        }

        async function fireAt(x, y) {
            if (phase !== 'playing' || !currentGameId) return;

            const targetCell = document.getElementById(`b-${x}-${y}`);
            if (targetCell.dataset.fired) return;
            targetCell.dataset.fired = "true";

            const res = await fetch(`/api/games/${currentGameId}/fire`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ x, y })
            });

            const data = await res.json();
            if (data.error) return;

            const shot = data.player_shot;
            const coordStr = toCoordName(x, y);

            if (shot.result === 'hit' || shot.result === 'sunk') {
                targetCell.className = 'cell bg-rose-600 border border-rose-400 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-pulse';
                targetCell.innerText = '✕';
                log(`HỎA LỰC TRÚNG MỤC TIÊU tại [${coordStr}]! ${shot.result === 'sunk' ? 'XÁC NHẬN TÀU ĐỐI PHƯƠNG ĐÃ CHÌM!' : ''}`, 'text-emerald-400 font-bold');
            } else {
                targetCell.className = 'cell bg-slate-800/80 border border-slate-700 text-slate-500 rounded-sm';
                targetCell.innerText = '•';
                log(`Hỏa lực trượt tại [${coordStr}].`, 'text-slate-400');
            }

            if (data.game_status === 'won') {
                phase = 'ended';
                document.getElementById('gameStatusText').innerText = "CHIẾN THẮNG CHUNG CUỘC! TOÀN BỘ HẠM ĐỘI ĐỐI PHƯƠNG ĐÃ BỊ TIÊU DIỆT!";
                log("TẤT CẢ TÀU ĐỐI PHƯƠNG ĐÃ BỊ ĐÁNH CHÌM. BẠN CHIẾN THẮNG!", "text-yellow-300 font-bold text-sm");
                
                // Gọi mở modal nhập tên và thống kê điểm:
                if (data.stats) {
                    showVictoryModal(data.stats);
                }
                return;
            }

            if (data.bot_shot) {
                const bShot = data.bot_shot;
                const pCell = document.getElementById(`p-${bShot.x}-${bShot.y}`);
                const bCoordStr = toCoordName(bShot.x, bShot.y);

                setTimeout(() => {
                    if (bShot.result === 'hit' || bShot.result === 'sunk') {
                        pCell.className = 'cell bg-rose-600 border border-rose-300 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-bounce';
                        pCell.innerText = '✕';
                        log(`CẢNH BÁO: Tàu của ta trúng đạn tại [${bCoordStr}]!`, 'text-rose-400 font-bold');
                    } else {
                        pCell.className = 'cell bg-slate-800 border border-slate-700 text-slate-500 rounded-sm';
                        pCell.innerText = '•';
                        log(`Đối phương bắn trượt tại [${bCoordStr}].`, 'text-slate-500');
                    }

                    if (data.game_status === 'lost') {
                        phase = 'ended';
                        document.getElementById('gameStatusText').innerText = "THẤT BẠI TÁC CHIẾN! HẠM ĐỘI CỦA BẠN ĐÃ BỊ TIÊU DIỆT HOÀN TOÀN!";
                        log("TẤT CẢ TÀU CHIẾN CỦA TA ĐÃ CHÌM. NHIỆM VỤ THẤT BẠI!", "text-rose-500 font-bold text-sm");
                    }
                }, 350);
            }
        }

        renderEmptyBoards();
        renderShipButtons();

        let lastGameStats = null;

        async function loadLeaderboard() {
            let diff = document.getElementById('difficultySelect').value;
            if (diff === 'random') diff = 'medium'; // Xem tạm medium nếu chọn ngẫu nhiên

            document.getElementById('lbDifficultyTitle').innerText = diff.toUpperCase();

            const res = await fetch(`/api/leaderboard?difficulty=${diff}`);
            const data = await res.json();
            const tbody = document.getElementById('leaderboardBody');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-3 text-center text-slate-500 italic">Chưa có chỉ huy nào đánh bại cấp độ này. Hãy là người đầu tiên!</td></tr>';
                return;
            }

            data.forEach((p, i) => {
                const badgeColor = i === 0 ? 'text-amber-400 font-extrabold' : i === 1 ? 'text-slate-300' : i === 2 ? 'text-amber-600' : 'text-slate-500';
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-800/40 transition';
                row.innerHTML = `
                    <td class="py-2.5 px-3 ${badgeColor}">#${i + 1}</td>
                    <td class="py-2.5 px-3 font-bold text-white">${p.player_name}</td>
                    <td class="py-2.5 px-3 font-bold text-amber-300">${p.score.toLocaleString()}</td>
                    <td class="py-2.5 px-3 text-cyan-400">${p.duration_seconds}s</td>
                    <td class="py-2.5 px-3 text-emerald-400">${p.accuracy}%</td>
                    <td class="py-2.5 px-3 text-sky-400">${p.fleet_health}%</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Tự động load lại bảng rank khi người chơi đổi dropdown độ khó
        document.getElementById('difficultySelect').addEventListener('change', loadLeaderboard);

        // Hiển thị modal khi thắng
        function showVictoryModal(stats) {
            lastGameStats = stats;
            document.getElementById('resScore').innerText = stats.score.toLocaleString();
            document.getElementById('resTime').innerText = `${stats.duration_seconds}s`;
            document.getElementById('resAccuracy').innerText = `${stats.accuracy}%`;
            document.getElementById('resFleetHp').innerText = `${stats.fleet_health}%`;
            document.getElementById('victoryModal').classList.remove('hidden');
        }

        async function submitScore() {
            const name = document.getElementById('commanderName').value;
            await fetch(`/api/games/${currentGameId}/save-score`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ player_name: name })
            });

            document.getElementById('victoryModal').classList.add('hidden');
            loadLeaderboard();
        }

        // Trong fireAt(), khi data.game_status === 'won':
        // Gọi thêm hàm showVictoryModal(data.stats);

        // Gọi load bảng xếp hạng ngay khi vào trang
        loadLeaderboard();
    </script>
</body>
</html>