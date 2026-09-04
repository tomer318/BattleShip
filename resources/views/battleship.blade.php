<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Battleship War - Naval Tactical Command</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Chakra Petch', sans-serif;
            background-color: #080c14;
            background-image: 
                radial-gradient(ellipse at 50% 0%, rgba(6, 182, 212, 0.12), transparent 65%),
                radial-gradient(circle at 100% 100%, rgba(225, 29, 72, 0.07), transparent 45%);
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
        .select-tactical {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2306b6d4'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
        }
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
        @keyframes shockShake {
            0%, 100% { transform: translate(0, 0); }
            20% { transform: translate(-4px, 4px); }
            40% { transform: translate(4px, -4px); }
            60% { transform: translate(-3px, -3px); }
            80% { transform: translate(3px, 3px); }
        }
        .shock-effect {
            animation: shockShake 0.45s ease-in-out;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #090d16;
        }
        ::-webkit-scrollbar-thumb {
            background: #0e7490;
            border-radius: 4px;
            box-shadow: 0 0 8px rgba(6, 182, 212, 0.4);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #06b6d4;
        }
        html {
            scrollbar-width: thin;
            scrollbar-color: #0e7490 #090d16;
        }
    </style>
</head>
<body class="text-slate-100 min-h-screen p-6 antialiased selection:bg-cyan-500 selection:text-black">
    <div class="max-w-7xl mx-auto" id="gameAppContainer">
        <!-- Header HUD -->
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
            
            <div class="flex items-center gap-2.5 mt-3 sm:mt-0 font-sans">
                <!-- Hộp Profile HUD -->
                <div id="authUserBlock" class="h-[42px] hidden flex items-center gap-3 bg-slate-900/90 hover:bg-slate-850 border border-cyan-500/40 hover:border-cyan-400 rounded-lg px-3 transition shadow-[0_0_15px_rgba(6,182,212,0.12)]">
                    <div class="flex items-center gap-2.5 cursor-pointer" onclick="openModal('profileModal')">
                        <span class="text-amber-400 text-sm">🎖️</span>
                        <div class="text-left leading-tight">
                            <span id="navCommanderName" class="block text-[11px] font-bold text-cyan-300 tracking-wider truncate max-w-[90px]">Commander</span>
                            <div class="flex items-center gap-2 font-mono-tactical text-[11px]">
                                <span id="navCredits" class="text-amber-400 font-bold">$0</span>
                                <span id="navGems" class="text-fuchsia-400 font-bold">💎0</span>
                            </div>
                        </div>
                    </div>
                    <button onclick="logout()" title="Đăng xuất" class="text-slate-500 hover:text-rose-400 text-xs ml-1 transition">✕</button>
                </div>

                <div id="authGuestBlock" class="h-[42px] flex items-center gap-2">
                    <button onclick="openModal('loginModal')" class="h-full border border-cyan-500/40 hover:border-cyan-400 hover:bg-cyan-500/10 text-cyan-300 text-xs uppercase px-3.5 rounded-lg font-bold transition shadow-[0_0_12px_rgba(6,182,212,0.1)]">
                        Đăng Nhập
                    </button>
                    <button onclick="openModal('registerModal')" class="h-full bg-cyan-600/80 hover:bg-cyan-500 text-white text-xs uppercase px-3.5 rounded-lg font-bold transition shadow-[0_0_12px_rgba(6,182,212,0.25)]">
                        Đăng Ký
                    </button>
                </div>

                <button onclick="openShopModal()" class="h-[42px] px-3.5 rounded-lg border border-amber-500/40 bg-slate-900/90 hover:bg-amber-500/10 hover:border-amber-400 text-amber-300 font-bold text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(245,158,11,0.12)] hover:shadow-[0_0_20px_rgba(245,158,11,0.25)] flex items-center gap-2">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    <span class="tracking-wider">QUÂN NHU</span>
                    <span class="text-[10px] text-amber-400/80 border border-amber-500/30 px-1 rounded font-mono-tactical">SHOP</span>
                </button>

                <!-- Nút Bật/Tắt SFX -->
                <button id="sfxToggleBtn" onclick="toggleSFX()" class="h-[42px] px-3 rounded-lg border border-cyan-500/40 bg-slate-900/90 hover:bg-cyan-500/10 hover:border-cyan-400 text-cyan-300 font-bold text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(6,182,212,0.12)] flex items-center gap-1.5" title="Bật/Tắt Âm Thanh">
                    <span id="sfxIcon">🔊</span>
                    <span id="sfxText" class="font-mono-tactical hidden sm:inline text-[11px]">SFX: BẬT</span>
                </button>

                <!-- Nút Mở Phòng PvP Online -->
                <button onclick="openPvpModal()" class="h-[42px] px-3.5 rounded-lg border border-indigo-500/40 bg-slate-900/90 hover:bg-indigo-500/10 hover:border-indigo-400 text-indigo-300 font-bold text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(99,102,241,0.12)] flex items-center gap-2">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-indigo-400 animate-ping"></span>
                    <span>⚔️ ĐẤU MẠNG PVP</span>
                </button>

                <div class="relative h-[42px]">
                    <select id="difficultySelect" class="select-tactical h-full font-semibold bg-slate-900/90 text-cyan-300 border border-cyan-500/40 hover:border-cyan-400 rounded-lg pl-3.5 pr-8 text-xs tracking-wider shadow-[0_0_15px_rgba(6,182,212,0.12)] focus:outline-none focus:ring-1 focus:ring-cyan-400 cursor-pointer transition">
                        <option value="random" class="bg-slate-900 text-amber-300">🎲 Ngẫu nhiên (Bí ẩn)</option>
                        <option value="easy" class="bg-slate-900 text-slate-200">Dễ (Ngẫu nhiên)</option>
                        <option value="medium" class="bg-slate-900 text-slate-200" selected>Trung bình (Hunt / Target)</option>
                        <option value="hard" class="bg-slate-900 text-slate-200">Khó (Parity Checkerboard)</option>
                        <option value="nightmare" class="bg-slate-900 text-rose-400">Cực khó (Bản đồ xác suất)</option>
                    </select>
                </div>

                <button onclick="resetSetup()" class="h-[42px] bg-slate-900/90 hover:bg-slate-800 hover:text-cyan-300 text-slate-300 border border-slate-700/80 hover:border-slate-500 px-4 rounded-lg text-xs font-bold tracking-wider uppercase transition shadow-[0_0_12px_rgba(0,0,0,0.4)] flex items-center justify-center">
                    XẾP LẠI
                </button>
            </div>
        </header>

        <!-- Thanh công cụ dàn trận -->
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

        <!-- HUD ĐỒNG HỒ ĐẾM NGƯỢC LƯỢT ĐÁNH (TURN TIMER) -->
        <div id="turnTimerContainer" class="hidden mb-4 max-w-xl mx-auto bg-slate-900/90 border border-cyan-500/40 rounded-xl p-3 shadow-[0_0_20px_rgba(6,182,212,0.15)]">
            <div class="flex justify-between items-center text-xs font-mono-tactical mb-1.5">
                <span id="turnTimerLabel" class="font-bold text-cyan-300 uppercase flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                    <span>LƯỢT CỦA BẠN - KHAI HỎA:</span>
                </span>
                <span id="turnTimerText" class="text-base font-black text-cyan-300">15s</span>
            </div>
            <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-slate-800 p-[1px]">
                <div id="turnTimerBar" class="h-full bg-cyan-400 rounded-full transition-all duration-1000 ease-linear shadow-[0_0_8px_rgba(6,182,212,0.8)]" style="width: 100%;"></div>
            </div>
        </div>

        <!-- BANNER BÁO ĐỘNG KÍCH HOẠT KỸ NĂNG -->
        <div id="skillAlertBanner" class="hidden my-3 p-3 bg-gradient-to-r from-rose-950 via-slate-900 to-rose-950 border-2 border-rose-500 rounded-xl text-center shadow-[0_0_30px_rgba(244,63,94,0.4)] animate-pulse">
            <span class="text-xs uppercase font-extrabold tracking-widest text-rose-400 flex items-center justify-center gap-2">
                <span class="text-base">⚠️</span> <span id="skillAlertText">BOT ĐÃ KÍCH HOẠT VŨ KHÍ BÍ MẬT!</span> <span class="text-base">⚠️</span>
            </span>
        </div>

        <!-- KHU VỰC CHIẾN TRƯỜNG CHÍNH -->
        <main class="flex flex-wrap justify-center items-start gap-4 lg:gap-6 mt-2">
            <!-- CỘT 1: KỸ NĂNG TA -->
            <div class="w-28 flex flex-col items-center bg-slate-900/80 border border-cyan-500/40 rounded-xl p-2.5 shadow-[0_0_20px_rgba(6,182,212,0.15)] self-stretch justify-start">
                <span class="text-[10px] uppercase font-extrabold text-cyan-400 tracking-wider mb-3 text-center border-b border-cyan-900/60 pb-1 w-full">
                    ⚡ KỸ NĂNG TA
                </span>
                <div id="playerSkillsList" class="flex flex-col gap-2 w-full">
                    <span class="text-[10px] text-slate-500 italic text-center">Trống</span>
                </div>
            </div>

            <!-- CỘT 2: BÀN CỜ TA -->
            <div class="flex flex-col items-center">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                    <h2 class="text-base font-bold tracking-wider text-cyan-300 uppercase">Hạm Đội Của Bạn</h2>
                </div>
                <div id="playerGrid" class="grid-board bg-slate-900/80 p-2.5 rounded-xl border-2 border-cyan-900/70 shadow-[0_0_20px_rgba(6,182,212,0.1)] transition-all"></div>
            </div>

            <!-- CỘT 3: BÀN CỜ ĐỊCH -->
            <div class="flex flex-col items-center">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <h2 class="text-base font-bold tracking-wider text-rose-400 uppercase">Vùng Biển Đối Phương</h2>
                </div>
                <div id="botGrid" class="grid-board radar-scan bg-slate-900/80 p-2.5 rounded-xl border-2 border-rose-950/70 opacity-40 pointer-events-none transition-all shadow-[0_0_20px_rgba(244,63,94,0.08)]"></div>
            </div>

            <!-- CỘT 4: KHO ĐỊCH -->
            <div class="w-28 flex flex-col items-center bg-slate-900/80 border border-rose-500/40 rounded-xl p-2.5 shadow-[0_0_20px_rgba(244,63,94,0.15)] self-stretch justify-start">
                <span class="text-[10px] uppercase font-extrabold text-rose-400 tracking-wider mb-3 text-center border-b border-rose-900/60 pb-1 w-full">
                    ⚠️ KHO ĐỊCH
                </span>
                <div id="botSkillsList" class="flex flex-col gap-2 w-full">
                    <span class="text-[10px] text-slate-500 italic text-center">Chờ quét...</span>
                </div>
            </div>
        </main>

        <!-- NHẬT KÝ TÁC CHIẾN -->
        <div class="mt-4 bg-slate-900/80 backdrop-blur-md p-3.5 rounded-xl border border-cyan-900/60 max-w-3xl mx-auto h-28 overflow-y-auto font-mono-tactical text-xs shadow-[0_0_20px_rgba(6,182,212,0.1)]" id="battleLog">
            <div class="text-slate-500 italic">> Hệ thống chỉ huy tác chiến đã sẵn sàng...</div>
        </div>

        <!-- Bảng xếp hạng Top 5 -->
        <div class="mt-6 bg-slate-900/80 backdrop-blur-md p-5 rounded-xl border border-cyan-900/60 max-w-4xl mx-auto shadow-[0_0_25px_rgba(6,182,212,0.15)]">
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

        <!-- Modal Vinh Danh Thắng Trận -->
        <div id="victoryModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border-2 border-amber-400/80 p-6 rounded-2xl max-w-md w-full text-center shadow-[0_0_40px_rgba(251,191,36,0.3)]">
                <span class="text-4xl">🎖️</span>
                <h2 class="text-2xl font-black text-amber-400 uppercase tracking-widest mt-2">CHIẾN THẮNG HUY HOÀNG</h2>
                <p class="text-xs text-slate-400 mt-1">Toàn bộ hạm đội đối phương đã bị tiêu diệt</p>

                <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-4 my-4 grid grid-cols-2 gap-3 text-left font-mono-tactical text-xs">
                    <div>
                        <span class="text-slate-500 block">ĐIỂM SỐ:</span>
                        <span id="resScore" class="text-lg font-bold text-amber-300">0</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">THỜI GIAN:</span>
                        <span id="resTime" class="text-base font-semibold text-cyan-400">0s</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">CHÍNH XÁC:</span>
                        <span id="resAccuracy" class="text-sm font-semibold text-emerald-400">0%</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">BẢO TOÀN:</span>
                        <span id="resFleetHp" class="text-sm font-semibold text-sky-400">0%</span>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-amber-950/40 via-slate-950 to-fuchsia-950/40 border border-amber-500/30 rounded-xl p-3 mb-4 flex justify-around items-center font-mono-tactical">
                    <div class="text-center">
                        <span class="text-[10px] text-slate-400 block uppercase">Thưởng Ngân Sách</span>
                        <span id="rewardCredits" class="text-base font-extrabold text-amber-400">+$0</span>
                    </div>
                    <div class="w-[1px] h-8 bg-slate-800"></div>
                    <div class="text-center">
                        <span class="text-[10px] text-slate-400 block uppercase">Kim Cương Tác Chiến</span>
                        <span id="rewardGems" class="text-base font-extrabold text-fuchsia-400">+0 💎</span>
                    </div>
                </div>

                <div class="mb-4 text-left">
                    <label class="block text-xs font-semibold text-slate-400 mb-1 tracking-wider uppercase">Ký danh Chỉ Huy:</label>
                    <input id="commanderName" type="text" maxlength="15" placeholder="Commander X" class="w-full bg-slate-950 border border-cyan-500/50 rounded-lg px-3 py-2 text-sm text-cyan-300 focus:outline-none focus:border-cyan-400 font-mono-tactical">
                </div>

                <button onclick="submitScore()" class="w-full bg-amber-500 hover:bg-amber-400 text-slate-950 font-black tracking-widest uppercase py-2.5 rounded-lg transition shadow-[0_0_15px_rgba(245,158,11,0.4)]">
                    Ghi Danh Vào Bảng Rank
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Kho Quân Nhu -->
    <div id="shopModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-slate-950/95 border border-amber-500/40 p-6 rounded-2xl max-w-4xl w-full shadow-[0_0_45px_rgba(245,158,11,0.18)] flex flex-col max-h-[92vh] relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-amber-400 to-transparent"></div>
            
            <div class="flex flex-wrap justify-between items-center border-b border-slate-800 pb-3 mb-3 gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-2.5 rounded-sm bg-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.8)]"></span>
                    <div>
                        <h2 class="text-lg font-extrabold tracking-widest text-amber-300 uppercase">KHO QUÂN NHU TÁC CHIẾN</h2>
                        <span class="text-[10px] text-slate-500 font-mono-tactical uppercase tracking-wider">TACTICAL ARMORY TERMINAL // V2.0</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 font-mono-tactical">
                    <div class="bg-slate-900 border border-slate-800 px-2.5 py-1 rounded-md text-right">
                        <span class="text-[9px] text-slate-400 block uppercase">Ngân Khố</span>
                        <span id="shopCredits" class="text-xs font-black text-amber-400">$0</span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 px-2.5 py-1 rounded-md text-right">
                        <span class="text-[9px] text-slate-400 block uppercase">Kim Cương</span>
                        <span id="shopGems" class="text-xs font-black text-fuchsia-400">💎0</span>
                    </div>
                    <button onclick="closeModal('shopModal')" class="text-slate-500 hover:text-rose-400 text-lg ml-2 font-mono-tactical">✕</button>
                </div>
            </div>

            <div class="bg-slate-900/80 border border-slate-800/80 rounded-xl px-4 py-2.5 mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-xs font-mono-tactical">
                    <span class="text-amber-400 animate-spin text-sm">⏳</span>
                    <span class="text-slate-400">Đợt Restock tiếp theo:</span>
                    <span id="restockTimer" class="font-extrabold text-cyan-300 tracking-wider">00:00:00</span>
                </div>
                
                <button onclick="resetShopRestock()" class="bg-gradient-to-r from-fuchsia-700 to-purple-700 hover:from-fuchsia-600 hover:to-purple-600 text-white text-[11px] font-bold uppercase px-3 py-1.5 rounded-lg transition shadow-[0_0_15px_rgba(192,132,252,0.3)] flex items-center gap-1.5">
                    <span>⚡</span> LÀM MỚI SHOP NGAY (<strong class="text-fuchsia-200">25 💎</strong>)
                </button>
            </div>

            <div class="flex gap-2 border-b border-slate-800 mb-3 text-xs font-bold uppercase">
                <button id="tabDailyBtn" onclick="switchShopTab('daily')" class="px-4 py-2 border-b-2 border-amber-400 text-amber-300 transition">
                    📦 Quân Nhu Hàng Ngày ($)
                </button>
                <button id="tabGemsBtn" onclick="switchShopTab('gems')" class="px-4 py-2 border-b-2 border-transparent text-slate-400 hover:text-fuchsia-300 transition flex items-center gap-1">
                    <span>💎</span> Chợ Đen Vô Cực (Không Giới Hạn)
                </button>
            </div>

            <div id="shopTabContent" class="overflow-y-auto pr-2 flex-1 space-y-4"></div>
        </div>
    </div>

    <!-- Modal Phòng Đấu Mạng PvP -->
    <div id="pvpModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border-2 border-indigo-500/60 p-6 rounded-2xl max-w-md w-full shadow-[0_0_40px_rgba(99,102,241,0.3)] text-center">
            <span class="text-3xl">⚔️</span>
            <h2 class="text-xl font-black text-indigo-300 uppercase tracking-widest mt-2">ĐẤU TRƯỜNG PVP ONLINE</h2>
            <p class="text-xs text-slate-400 mt-1 mb-5">Thách đấu trực tiếp cùng các Chỉ Huy khác qua mạng</p>

            <!-- Giao diện khi chưa vào phòng -->
            <div id="pvpMenuSection" class="space-y-4">
                <button onclick="createPvpRoom()" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-black uppercase rounded-xl tracking-wider transition shadow-[0_0_20px_rgba(99,102,241,0.4)] flex items-center justify-center gap-2">
                    <span>📡</span> TẠO PHÒNG CHIẾN MỚI
                </button>

                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t border-slate-800"></div>
                    <span class="flex-shrink mx-4 text-slate-500 text-xs font-mono-tactical uppercase">Hoặc gia nhập</span>
                    <div class="flex-grow border-t border-slate-800"></div>
                </div>

                <div class="space-y-2 text-left">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Nhập mã phòng chiến:</label>
                    <div class="flex gap-2">
                        <input id="inputRoomCode" type="text" maxlength="15" placeholder="VD: ROOM-ABC12" class="w-full bg-slate-950 border border-indigo-500/40 rounded-lg px-3 py-2 text-sm text-indigo-300 focus:outline-none focus:border-indigo-400 font-mono-tactical uppercase">
                        <button onclick="joinPvpRoom()" class="px-4 py-2 bg-slate-800 hover:bg-slate-750 text-indigo-300 border border-indigo-500/40 font-bold text-xs uppercase rounded-lg transition">VÀO</button>
                    </div>
                </div>
            </div>

            <!-- Giao diện khi đã tạo phòng / chờ đối thủ -->
            <div id="pvpWaitingSection" class="hidden space-y-4 font-mono-tactical">
                <div class="bg-slate-950 border border-indigo-500/40 rounded-xl p-4">
                    <span class="text-xs text-slate-400 block uppercase mb-1">Mã Phòng Tác Chiến Của Bạn:</span>
                    <span id="displayRoomCode" class="text-xl font-black text-amber-400 tracking-widest">ROOM-XXXXX</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-xs text-indigo-300 animate-pulse py-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-indigo-400 animate-ping"></span>
                    <span>Đang chờ Chỉ Huy thứ 2 kết nối vào phòng...</span>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800">
                <button onclick="closeModal('pvpModal')" class="w-full py-2 bg-slate-800 hover:bg-slate-750 text-slate-300 rounded-lg text-xs font-bold tracking-wider uppercase">
                    Đóng Lại
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Hồ Sơ Tác Chiến & Quân Công Thành Tựu -->
    <div id="profileModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border-2 border-cyan-500/60 p-6 rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-[0_0_40px_rgba(6,182,212,0.25)]">
            <div class="flex justify-between items-center border-b border-cyan-900/50 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🎖️</span>
                    <div>
                        <h2 id="profCommanderName" class="text-lg font-black text-cyan-300 uppercase tracking-widest">COMMANDER</h2>
                        <span id="profEmail" class="text-xs text-slate-500 font-mono-tactical">email@example.com</span>
                    </div>
                </div>
                <div class="text-right font-mono-tactical flex items-center gap-3">
                    <div class="bg-slate-950 px-2.5 py-1 rounded border border-slate-800 text-right">
                        <span class="text-[9px] text-slate-500 block uppercase">Ngân Sách</span>
                        <span id="profCredits" class="text-xs font-bold text-amber-400">$0</span>
                    </div>
                    <div class="bg-slate-950 px-2.5 py-1 rounded border border-slate-800 text-right">
                        <span class="text-[9px] text-slate-500 block uppercase">Kim Cương</span>
                        <span id="profGems" class="text-xs font-bold text-fuchsia-400">💎0</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 border-b border-slate-800 mb-3 text-xs font-bold uppercase">
                <button id="tabProfStatsBtn" onclick="switchProfileTab('stats')" class="px-3 py-2 border-b-2 border-cyan-400 text-cyan-300 transition">
                    📊 Chiến Tích Đối Thủ
                </button>
                <button id="tabProfAchPveBtn" onclick="switchProfileTab('pve')" class="px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-amber-300 transition flex items-center gap-1.5">
                    <span>🤖</span> Thành Tựu Chiến Dịch (Bot)
                </button>
                <button id="tabProfAchPvpBtn" onclick="switchProfileTab('pvp')" class="px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-fuchsia-300 transition flex items-center gap-1.5">
                    <span>⚔️</span> Thành Tựu Đấu Mạng (PvP)
                </button>
            </div>

            <div class="overflow-y-auto pr-1 flex-1 space-y-3">
                <div id="profTabStatsContent" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3 font-mono-tactical text-xs" id="profStatsGrid"></div>
                </div>

                <div id="profTabAchContent" class="hidden space-y-2.5"></div>
            </div>

            <div class="pt-4 border-t border-slate-800 mt-2">
                <button onclick="closeModal('profileModal')" class="w-full py-2 bg-slate-800 hover:bg-slate-750 text-cyan-300 border border-slate-700 rounded-lg text-xs font-bold tracking-wider uppercase">
                    Đóng Hồ Sơ
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Đăng Nhập -->
    <div id="loginModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-cyan-500/60 p-6 rounded-2xl max-w-sm w-full text-center shadow-[0_0_30px_rgba(6,182,212,0.3)]">
            <h2 class="text-xl font-bold text-cyan-300 uppercase tracking-wider mb-4">Xác Thực Chỉ Huy</h2>
            <form onsubmit="handleLogin(event)" class="space-y-3 text-left">
                <div>
                    <label class="text-xs text-slate-400 uppercase font-semibold">Email tác chiến:</label>
                    <input id="loginEmail" type="email" required class="w-full bg-slate-950 border border-slate-700 focus:border-cyan-400 rounded-lg p-2 text-sm text-cyan-200 font-mono-tactical outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 uppercase font-semibold">Mật mã bảo mật:</label>
                    <input id="loginPassword" type="password" required class="w-full bg-slate-950 border border-slate-700 focus:border-cyan-400 rounded-lg p-2 text-sm text-cyan-200 font-mono-tactical outline-none">
                </div>
                <div id="loginError" class="text-xs text-rose-400 hidden"></div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeModal('loginModal')" class="w-1/2 py-2 border border-slate-700 text-slate-400 rounded-lg text-xs uppercase font-bold">Hủy</button>
                    <button type="submit" class="w-1/2 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg text-xs uppercase font-bold tracking-wider shadow">Truy Cập</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Đăng Ký -->
    <div id="registerModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-cyan-500/60 p-6 rounded-2xl max-w-sm w-full text-center shadow-[0_0_30px_rgba(6,182,212,0.3)]">
            <h2 class="text-xl font-bold text-cyan-300 uppercase tracking-wider mb-4">Đăng Ký Quân Tịch</h2>
            <form onsubmit="handleRegister(event)" class="space-y-3 text-left">
                <div>
                    <label class="text-xs text-slate-400 uppercase font-semibold">Tên Chỉ Huy (Ký danh):</label>
                    <input id="regName" type="text" maxlength="20" required class="w-full bg-slate-950 border border-slate-700 focus:border-cyan-400 rounded-lg p-2 text-sm text-cyan-200 font-mono-tactical outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 uppercase font-semibold">Email liên lạc:</label>
                    <input id="regEmail" type="email" required class="w-full bg-slate-950 border border-slate-700 focus:border-cyan-400 rounded-lg p-2 text-sm text-cyan-200 font-mono-tactical outline-none">
                </div>
                <div>
                    <label class="text-xs text-slate-400 uppercase font-semibold">Mật mã bảo mật (từ 6 ký tự):</label>
                    <input id="regPassword" type="password" minlength="6" required class="w-full bg-slate-950 border border-slate-700 focus:border-cyan-400 rounded-lg p-2 text-sm text-cyan-200 font-mono-tactical outline-none">
                </div>
                <div id="regError" class="text-xs text-rose-400 hidden"></div>
                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeModal('registerModal')" class="w-1/2 py-2 border border-slate-700 text-slate-400 rounded-lg text-xs uppercase font-bold">Hủy</button>
                    <button type="submit" class="w-1/2 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs uppercase font-bold tracking-wider shadow">Gia Nhập</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let currentUser = null;

        const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        const SHIPS_DATA = [
            { name: 'Carrier', size: 5 },
            { name: 'Battleship', size: 4 },
            { name: 'Cruiser', size: 3 },
            { name: 'Submarine', size: 3 },
            { name: 'Destroyer', size: 2 }
        ];

        const POWERUP_NAMES = {
            recon_sat: '🛰️ Vệ Tinh',
            recon_scan: '📡 Radar 3x3',
            recon_sonar: '🔊 Sonar 5x5',
            combat_guided: '🚀 Tên Lửa',
            combat_airstrike: '✈️ Không Kích',
            combat_smokescreen: '💨 Màn Khói',
            def_shield: '🛡️ Khiên Chắn',
            def_repair_relocate: '🔧 Tái Cấu Trúc',
            def_tactical_relocate: '⚓ Cơ Động'
        };

        const SHOP_ITEMS_SPEC = {
            recon_sat: { name: 'Vệ Tinh Quét Vị Trí', cat: 'recon', catName: 'TRINH SÁT', price: 250, gemPrice: 35, limit: 1, desc: 'Lộ chính xác 1 ô có tàu địch còn sống và tên loại tàu.' },
            recon_scan: { name: 'Radar Vùng 3x3', cat: 'recon', catName: 'TRINH SÁT', price: 100, gemPrice: 18, limit: 3, desc: 'Quét vùng 3x3: Báo tổng số ô chứa tàu đang ẩn nấp.' },
            recon_sonar: { name: 'Sonar Cảm Biến 5x5', cat: 'recon', catName: 'TRINH SÁT', price: 40, gemPrice: 8, limit: 5, desc: 'Báo khoảng cách: Rất gần, Gần, Xa, Rất xa trong bán kính 5x5.' },

            combat_guided: { name: 'Tên Lửa Dẫn Đường', cat: 'combat', catName: 'HỎA LỰC TÁC CHIẾN', price: 300, gemPrice: 35, limit: 1, desc: 'Đánh trúng ngay 1 ô tàu địch còn nguyên và lộ danh tính tàu.' },
            combat_airstrike: { name: 'Không Kích Phá Rối', cat: 'combat', catName: 'HỎA LỰC TÁC CHIẾN', price: 150, gemPrice: 18, limit: 2, desc: 'Yêu cầu Carrier còn nổi: Tước quyền phản công 1 lượt của địch.' },
            combat_smokescreen: { name: 'Màn Khói Nhiễu Loạn', cat: 'combat', catName: 'HỎA LỰC TÁC CHIẾN', price: 60, gemPrice: 8, limit: 4, desc: 'Ẩn kết quả trúng/trượt trong 5 phát đạn tiếp theo của địch.' },

            def_shield: { name: 'Khiên Năng Lượng', cat: 'defend', catName: 'PHÒNG THỦ & ĐIỀU ĐỘNG', price: 280, gemPrice: 35, limit: 1, desc: 'Tạo lá chắn: Vô hiệu hóa 3 phát đạn trúng tiếp theo (tối đa 5 lượt).' },
            def_repair_relocate: { name: 'Tái Cấu Trúc Khẩn Cấp', cat: 'defend', catName: 'PHÒNG THỦ & ĐIỀU ĐỘNG', price: 160, gemPrice: 18, limit: 2, desc: 'Phục hồi 100% thân tàu bị tổn thương và di chuyển sang tọa độ an toàn.' },
            def_tactical_relocate: { name: 'Cơ Động Chiến Thuật', cat: 'defend', catName: 'PHÒNG THỦ & ĐIỀU ĐỘNG', price: 70, gemPrice: 8, limit: 3, desc: 'Điều động 1 tàu nguyên vẹn đến vị trí hải đồ ngẫu nhiên chưa bị lộ.' },
        };

        let currentShopTab = 'daily';
        let isHorizontal = true;
        let selectedShipIndex = 0;
        let placedShips = []; 
        let phase = 'setup';
        let currentGameId = null;
        let targetingSkill = null;
        let lastGameStats = null;
        let botItemSlots = [];

        let currentPvpRoomCode = null;
        let gameMode = 'pve';
        let myPvpRole = null;  

        const TURN_TIME_LIMIT = 15;
        let turnTimeLeft = TURN_TIME_LIMIT;
        let turnTimerInterval = null;

        let sfxEnabled = true;
        let audioCtx = null;

        function getAudioContext() {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AudioContext();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        function toggleSFX() {
            sfxEnabled = !sfxEnabled;
            document.getElementById('sfxIcon').innerText = sfxEnabled ? '🔊' : '🔇';
            document.getElementById('sfxText').innerText = sfxEnabled ? 'SFX: BẬT' : 'SFX: TẮT';
            const btn = document.getElementById('sfxToggleBtn');
            btn.className = sfxEnabled 
                ? 'h-[42px] px-3 rounded-lg border border-cyan-500/40 bg-slate-900/90 hover:bg-cyan-500/10 hover:border-cyan-400 text-cyan-300 font-bold text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(6,182,212,0.12)] flex items-center gap-1.5'
                : 'h-[42px] px-3 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-500 font-bold text-xs uppercase tracking-wider transition flex items-center gap-1.5';
            
            if (sfxEnabled) playSFX('click');
        }

        const SoundFX = {
            fire() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(140, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(30, ctx.currentTime + 0.25);

                gain.gain.setValueAtTime(0.4, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            },

            hit() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const bufferSize = ctx.sampleRate * 0.4;
                const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) {
                    data[i] = Math.random() * 2 - 1;
                }

                const noise = ctx.createBufferSource();
                noise.buffer = buffer;

                const filter = ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.setValueAtTime(900, ctx.currentTime);
                filter.frequency.exponentialRampToValueAtTime(50, ctx.currentTime + 0.4);

                const gain = ctx.createGain();
                gain.gain.setValueAtTime(0.6, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);

                noise.connect(filter);
                filter.connect(gain);
                gain.connect(ctx.destination);

                noise.start();
                noise.stop(ctx.currentTime + 0.4);
            },

            miss() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(320, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(80, ctx.currentTime + 0.2);

                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            },

            sunk() {
                if (!sfxEnabled) return;
                this.hit();
                setTimeout(() => this.hit(), 160);
                setTimeout(() => this.hit(), 320);
            },

            shield() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'triangle';
                osc.frequency.setValueAtTime(580, ctx.currentTime);
                osc.frequency.linearRampToValueAtTime(1100, ctx.currentTime + 0.12);
                osc.frequency.linearRampToValueAtTime(450, ctx.currentTime + 0.35);

                gain.gain.setValueAtTime(0.35, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.4);
            },

            sonar() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(1250, ctx.currentTime);

                gain.gain.setValueAtTime(0.35, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.7);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.7);
            },

            alarm() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(650, ctx.currentTime);
                osc.frequency.linearRampToValueAtTime(950, ctx.currentTime + 0.15);
                osc.frequency.linearRampToValueAtTime(650, ctx.currentTime + 0.3);

                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            },

            victory() {
                if (!sfxEnabled) return;
                const notes = [523.25, 659.25, 783.99, 1046.50];
                notes.forEach((freq, idx) => {
                    setTimeout(() => {
                        const ctx = getAudioContext();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();

                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime);

                        gain.gain.setValueAtTime(0.25, ctx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);

                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        osc.stop(ctx.currentTime + 0.4);
                    }, idx * 130);
                });
            },

            click() {
                if (!sfxEnabled) return;
                const ctx = getAudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, ctx.currentTime);
                gain.gain.setValueAtTime(0.05, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.05);
            }
        };

        function playSFX(name) {
            if (SoundFX[name]) SoundFX[name]();
        }

        function startTurnTimer(isPlayerTurn = true) {
            clearInterval(turnTimerInterval);
            if (phase !== 'playing') {
                document.getElementById('turnTimerContainer').classList.add('hidden');
                return;
            }

            const container = document.getElementById('turnTimerContainer');
            const label = document.getElementById('turnTimerLabel');
            const timerText = document.getElementById('turnTimerText');
            const timerBar = document.getElementById('turnTimerBar');

            container.classList.remove('hidden');
            turnTimeLeft = TURN_TIME_LIMIT;

            if (isPlayerTurn) {
                label.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span><span>LƯỢT CỦA BẠN - KHAI HỎA:</span>`;
                timerText.className = "text-base font-black text-cyan-300 font-mono-tactical";
                timerBar.className = "h-full bg-cyan-400 rounded-full transition-all duration-1000 ease-linear shadow-[0_0_8px_rgba(6,182,212,0.8)]";
            } else {
                label.innerHTML = `<span class="inline-block w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span><span>ĐỐI PHƯƠNG ĐANG KHÓA MỤC TIÊU...</span>`;
                timerText.className = "text-base font-black text-rose-400 font-mono-tactical";
                timerBar.className = "h-full bg-rose-500 rounded-full transition-all duration-1000 ease-linear shadow-[0_0_8px_rgba(244,63,94,0.8)]";
            }

            updateTimerHUD(turnTimeLeft);

            turnTimerInterval = setInterval(() => {
                turnTimeLeft--;
                updateTimerHUD(turnTimeLeft);

                if (turnTimeLeft <= 0) {
                    clearInterval(turnTimerInterval);
                    handleTurnTimeout(isPlayerTurn);
                }
            }, 1000);
        }

        function updateTimerHUD(seconds) {
            const timerText = document.getElementById('turnTimerText');
            const timerBar = document.getElementById('turnTimerBar');
            const percent = Math.max(0, (seconds / TURN_TIME_LIMIT) * 100);

            timerText.innerText = `${seconds}s`;
            timerBar.style.width = `${percent}%`;

            if (seconds <= 5) {
                timerText.className = "text-base font-black text-rose-400 animate-bounce font-mono-tactical";
                timerBar.className = "h-full bg-rose-500 rounded-full transition-all duration-1000 ease-linear shadow-[0_0_12px_rgba(244,63,94,1)]";
            } else if (seconds <= 8) {
                timerText.className = "text-base font-black text-amber-400 font-mono-tactical";
                timerBar.className = "h-full bg-amber-400 rounded-full transition-all duration-1000 ease-linear shadow-[0_0_8px_rgba(245,158,11,0.8)]";
            }
        }

        async function handleTurnTimeout(isPlayerTurn) {
            if (phase !== 'playing' || !currentGameId) return;

            if (isPlayerTurn) {
                log(`HẾT GIỜ TÁC CHIẾN (15s)! Hệ thống hỏa lực bị quá tải, BẠN BỊ MẤT LƯỢT!`, 'text-rose-500 font-bold');
                triggerSkillAlert("QUÁ GIỜ KHAI HỎA: ĐỐI PHƯƠNG CHIẾM QUYỀN PHẢN KÍCH!", true);
                playSFX('alarm');

                const res = await fetch(`/api/games/${currentGameId}/fire`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ x: -1, y: -1, timeout: true })
                });
                const data = await res.json();

                if (data.bot_shot) {
                    handleBotShotResult(data.bot_shot);
                }
                if (data.game_status === 'playing') {
                    startTurnTimer(true);
                }
            }
        }

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

        function triggerSkillAlert(text, isBot = true) {
            const banner = document.getElementById('skillAlertBanner');
            const txt = document.getElementById('skillAlertText');
            txt.innerText = text;
            banner.className = isBot 
                ? 'my-3 p-3 bg-gradient-to-r from-rose-950 via-slate-900 to-rose-950 border-2 border-rose-500 rounded-xl text-center shadow-[0_0_30px_rgba(244,63,94,0.4)] animate-pulse'
                : 'my-3 p-3 bg-gradient-to-r from-cyan-950 via-slate-900 to-cyan-950 border-2 border-cyan-400 rounded-xl text-center shadow-[0_0_30px_rgba(6,182,212,0.4)] animate-pulse';
            
            banner.classList.remove('hidden');
            playSFX('alarm');
            setTimeout(() => banner.classList.add('hidden'), 4500);
        }

        function handleBotVisualEffects(effect) {
            if (!effect || !effect.type) return;

            if (effect.type === 'bot_radar_scan') {
                playSFX('sonar');
                for (let dx = -1; dx <= 1; dx++) {
                    for (let dy = -1; dy <= 1; dy++) {
                        const tx = effect.cx + dx;
                        const ty = effect.cy + dy;
                        const c = document.getElementById(`p-${tx}-${ty}`);
                        if (c) {
                            c.classList.add('bg-rose-600/50', 'animate-pulse');
                            setTimeout(() => c.classList.remove('bg-rose-600/50', 'animate-pulse'), 3500);
                        }
                    }
                }
            }

            if (effect.type === 'bot_sonar_pulse') {
                playSFX('sonar');
                for (let dx = -2; dx <= 2; dx++) {
                    for (let dy = -2; dy <= 2; dy++) {
                        const tx = effect.cx + dx;
                        const ty = effect.cy + dy;
                        const c = document.getElementById(`p-${tx}-${ty}`);
                        if (c) {
                            c.classList.add('bg-amber-600/40');
                            setTimeout(() => c.classList.remove('bg-amber-600/40'), 2500);
                        }
                    }
                }
            }

            if (effect.type === 'bot_missile_lock' && effect.target) {
                playSFX('alarm');
                const targetCell = document.getElementById(`p-${effect.target.x}-${effect.target.y}`);
                if (targetCell) {
                    targetCell.classList.add('bg-rose-700', 'animate-ping');
                    setTimeout(() => targetCell.classList.remove('bg-rose-700', 'animate-ping'), 3000);
                }
            }

            if (effect.type === 'bot_shield_activated') {
                playSFX('shield');
                const botGrid = document.getElementById('botGrid');
                botGrid.classList.add('border-amber-400', 'shadow-[0_0_35px_rgba(251,191,36,0.5)]');
                setTimeout(() => {
                    botGrid.classList.remove('border-amber-400', 'shadow-[0_0_35px_rgba(251,191,36,0.5)]');
                }, 4000);
            }

            if (effect.type === 'bot_smoke_active') {
                const botGrid = document.getElementById('botGrid');
                botGrid.classList.add('border-purple-500', 'shadow-[0_0_35px_rgba(168,85,247,0.4)]');
                setTimeout(() => {
                    botGrid.classList.remove('border-purple-500', 'shadow-[0_0_35px_rgba(168,85,247,0.4)]');
                }, 5000);
            }

            if (effect.type === 'bot_airstrike_shock') {
                playSFX('hit');
                const app = document.getElementById('gameAppContainer');
                app.classList.add('shock-effect');
                setTimeout(() => app.classList.remove('shock-effect'), 500);
            }

            if (effect.type === 'bot_satellite_sweep' && effect.coord) {
                playSFX('sonar');
                const c = document.getElementById(`p-${effect.coord.x}-${effect.coord.y}`);
                if (c) {
                    c.classList.add('bg-yellow-400/80', 'animate-pulse');
                    setTimeout(() => c.classList.remove('bg-yellow-400/80', 'animate-pulse'), 3000);
                }
            }

            if (effect.type === 'bot_ship_relocated') {
                const botGrid = document.getElementById('botGrid');
                botGrid.classList.add('border-emerald-500', 'shadow-[0_0_30px_rgba(16,185,129,0.3)]');
                setTimeout(() => {
                    botGrid.classList.remove('border-emerald-500', 'shadow-[0_0_30px_rgba(16,185,129,0.3)]');
                }, 3000);
            }
        }

        function toggleOrientation() {
            isHorizontal = !isHorizontal;
            document.getElementById('orientationText').innerText = isHorizontal ? 'Ngang' : 'Dọc';
            playSFX('click');
        }

        window.addEventListener('keydown', (e) => {
            if (e.key === 'r' || e.key === 'R') toggleOrientation();
        });

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            playSFX('click');
            if (id === 'profileModal') {
                loadAchievements().then(() => {
                    switchProfileTab('stats');
                });
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            playSFX('click');
        }

        async function checkCurrentUser() {
            try {
                const res = await fetch('/auth/me');
                const data = await res.json();
                if (data.authenticated) {
                    currentUser = data.user;
                    renderUserHUD();
                    loadAchievements();
                } else {
                    currentUser = null;
                    document.getElementById('authGuestBlock').classList.remove('hidden');
                    document.getElementById('authUserBlock').classList.add('hidden');
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderUserHUD() {
            if (!currentUser) return;
            document.getElementById('authGuestBlock').classList.add('hidden');
            document.getElementById('authUserBlock').classList.remove('hidden');
            document.getElementById('navCommanderName').innerText = currentUser.name;
            document.getElementById('navCredits').innerText = `$${(currentUser.credits || 0).toLocaleString()}`;
            document.getElementById('navGems').innerText = `💎${(currentUser.gems || 0).toLocaleString()}`;

            document.getElementById('profCommanderName').innerText = currentUser.name;
            document.getElementById('profEmail').innerText = currentUser.email;
            document.getElementById('profCredits').innerText = `$${(currentUser.credits || 0).toLocaleString()}`;
            document.getElementById('profGems').innerText = `💎${(currentUser.gems || 0).toLocaleString()}`;

            document.getElementById('shopCredits').innerText = `$${(currentUser.credits || 0).toLocaleString()}`;
            document.getElementById('shopGems').innerText = `💎${(currentUser.gems || 0).toLocaleString()}`;

            const statsContainer = document.getElementById('profStatsGrid');
            const diffMap = {
                easy: 'BOT DỄ',
                medium: 'BOT TRUNG BÌNH',
                hard: 'BOT KHÓ',
                nightmare: 'CỰC KHÓ (AI NGUYÊN TỬ)'
            };

            statsContainer.innerHTML = '';
            for (let [dKey, dName] of Object.entries(diffMap)) {
                const s = currentUser.stats?.[dKey] || { high_score: 0, wins: 0 };
                const box = document.createElement('div');
                box.className = 'bg-slate-950 p-3 rounded-lg border border-slate-800';
                box.innerHTML = `
                    <span class="text-cyan-400 font-bold block text-[11px] mb-1">${dName}</span>
                    <div class="flex justify-between text-slate-400 text-[11px]">
                        <span>Thắng: <strong class="text-emerald-400">${s.wins}</strong></span>
                        <span>Kỷ lục: <strong class="text-amber-300">${s.high_score.toLocaleString()}</strong></span>
                    </div>
                `;
                statsContainer.appendChild(box);
            }

            renderPlayerSkills();
        }

        function renderPlayerSkills() {
            const container = document.getElementById('playerSkillsList');
            container.innerHTML = '';

            if (!currentUser || !currentUser.inventory) {
                container.innerHTML = '<span class="text-[10px] text-slate-500 italic text-center">Đăng nhập để trang bị</span>';
                return;
            }

            let hasItems = false;
            for (let [k, qty] of Object.entries(currentUser.inventory)) {
                if (qty > 0 && POWERUP_NAMES[k]) {
                    hasItems = true;
                    const btn = document.createElement('button');
                    btn.className = 'w-full py-2 px-1 text-[10px] font-bold uppercase rounded border border-cyan-500/50 bg-slate-950 hover:bg-cyan-900/60 text-cyan-300 transition shadow flex flex-col items-center justify-center';
                    btn.innerHTML = `<span>${POWERUP_NAMES[k]}</span><span class="text-[9px] text-amber-400">SL: ${qty}</span>`;
                    btn.onclick = () => activatePlayerPowerup(k);
                    container.appendChild(btn);
                }
            }

            if (!hasItems) {
                container.innerHTML = '<span class="text-[10px] text-slate-500 italic text-center">Mở SHOP mua đồ</span>';
            }
        }

        function renderBotLoadoutSlots(count) {
            const container = document.getElementById('botSkillsList');
            container.innerHTML = '';
            botItemSlots = [];

            for (let i = 0; i < count; i++) {
                const slot = document.createElement('div');
                slot.className = 'w-full py-2 px-1 text-[10px] font-mono-tactical font-bold text-center rounded border border-rose-900/60 bg-slate-950 text-rose-500/80 shadow';
                slot.innerText = '[ ??? ]';
                slot.id = `bot-slot-${i}`;
                container.appendChild(slot);
                botItemSlots.push(slot);
            }
        }

        function revealBotPowerup(itemKey) {
            const label = POWERUP_NAMES[itemKey] || itemKey;
            let existingSlot = botItemSlots.find(s => s.innerText.includes(label));
            if (existingSlot) {
                existingSlot.className = 'w-full py-2 px-1 text-[10px] font-bold text-center rounded border border-amber-500 bg-amber-950/80 text-amber-300 shadow-[0_0_12px_rgba(245,158,11,0.6)] animate-pulse';
                existingSlot.innerText = `${label} (Lần 2)`;
                return;
            }

            const unrevealed = botItemSlots.find(s => s.innerText === '[ ??? ]');
            if (unrevealed) {
                unrevealed.innerText = label;
                unrevealed.className = 'w-full py-2 px-1 text-[10px] font-bold text-center rounded border border-rose-500 bg-rose-950/80 text-rose-300 shadow-[0_0_12px_rgba(244,63,94,0.6)] animate-pulse';
            }
        }

        function activatePlayerPowerup(itemId) {
            if (phase !== 'playing') {
                alert('Chỉ có thể kích hoạt kỹ năng khi trận chiến đang diễn ra!');
                return;
            }

            playSFX('click');

            if (itemId === 'recon_scan' || itemId === 'recon_sonar') {
                targetingSkill = itemId;
                const label = itemId === 'recon_scan' ? 'RADAR VÙNG 3X3' : 'SONAR CẢM BIẾN 5X5';
                document.getElementById('gameStatusText').innerText = `CHẾ ĐỘ ĐỊNH VỊ: Nhấp chuột vào 1 ô trên Vùng Biển Đối Phương để làm TÂM QUÉT [${label}]!`;
                log(`Đã chọn [${label}]: Vui lòng click chọn ô tâm quét trên vùng biển địch!`, 'text-amber-300 font-bold');
                return;
            }

            if (itemId === 'def_repair_relocate' || itemId === 'def_tactical_relocate') {
                targetingSkill = itemId;
                const label = itemId === 'def_repair_relocate' ? 'TÁI CẤU TRÚC' : 'CƠ ĐỘNG';
                document.getElementById('gameStatusText').innerText = `CHẾ ĐỘ ĐIỀU ĐỘNG: Nhấp chuột vào chiếc tàu trên Hạm Đội Của Bạn để áp dụng [${label}]!`;
                log(`Đã chọn [${label}]: Vui lòng click vào 1 tàu trên bàn cờ của bạn!`, 'text-amber-300 font-bold');
                return;
            }

            sendSkillExecution(itemId);
        }

        async function sendSkillExecution(itemId, extraParams = {}) {
            targetingSkill = null;
            clearInterval(turnTimerInterval);
            document.getElementById('gameStatusText').innerText = "Trận chiến đang diễn ra! Khai hỏa hoặc kích hoạt kỹ năng tác chiến.";

            // NẾU ĐANG CHƠI PVP ONLINE
            if (gameMode === 'pvp') {
                const roomCode = currentPvpRoomCode || currentRoomData?.room_code || currentRoomData?.room?.room_code;
                try {
                    const payload = Object.assign({ room_code: roomCode, item_id: itemId }, extraParams);
                    const res = await fetch('/api/pvp/use-powerup', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (res.ok) {
                        if (currentUser) {
                            currentUser.inventory = data.inventory;
                            renderPlayerSkills();
                        }
                        if (data.effect) {
                            handlePvpSkillEffect(data.effect);
                        }
                    } else {
                        alert(data.error || 'Lỗi kích hoạt kỹ năng PvP!');
                        startTurnTimer(true);
                    }
                } catch (err) {
                    console.error("Lỗi kỹ năng PvP:", err);
                }
                return;
            }

            // NẾU ĐANG ĐÁNH VỚI BOT (PVE)
            if (!currentGameId) return;

            try {
                const payload = Object.assign({ item_id: itemId }, extraParams);
                const res = await fetch(`/api/games/${currentGameId}/use-powerup`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok) {
                    currentUser.inventory = data.inventory;
                    renderPlayerSkills();

                    if (itemId === 'recon_sonar' || itemId === 'recon_scan' || itemId === 'recon_sat') {
                        playSFX('sonar');
                    } else if (itemId === 'def_shield') {
                        playSFX('shield');
                    } else {
                        playSFX('click');
                    }

                    if (data.effect) {
                        log(`[KỸ NĂNG] ${data.effect.msg}`, 'text-amber-300 font-bold');
                        triggerSkillAlert(data.effect.msg, false);

                        if (data.effect.type === 'reveal') {
                            const cell = document.getElementById(`b-${data.effect.x}-${data.effect.y}`);
                            if (cell) {
                                cell.className = 'cell bg-amber-400 border border-amber-200 text-black font-black animate-ping rounded-sm';
                                cell.innerText = '🎯';
                            }
                        }

                        if (data.effect.type === 'scan_3x3') {
                            for (let dx = -1; dx <= 1; dx++) {
                                for (let dy = -1; dy <= 1; dy++) {
                                    const tx = data.effect.cx + dx;
                                    const ty = data.effect.cy + dy;
                                    const c = document.getElementById(`b-${tx}-${ty}`);
                                    if (c && !c.dataset.fired) {
                                        c.classList.add('bg-cyan-500/50', 'animate-pulse');
                                        setTimeout(() => c.classList.remove('bg-cyan-500/50', 'animate-pulse'), 3000);
                                    }
                                }
                            }
                        }

                        if (data.player_ships) {
                            placedShips = data.player_ships;
                            const shipMap = {};
                            data.player_ships.forEach(ship => {
                                ship.coordinates.forEach(c => {
                                    shipMap[`${c.x},${c.y}`] = true;
                                });
                            });

                            for (let y = 0; y < 10; y++) {
                                for (let x = 0; x < 10; x++) {
                                    const el = document.getElementById(`p-${x}-${y}`);
                                    if (!el) continue;

                                    const isShip = !!shipMap[`${x},${y}`];
                                    const hasHit = el.innerText.trim() === '✕';
                                    const hasMiss = el.innerText.trim() === '•';

                                    if (hasHit) {
                                        el.className = 'cell bg-rose-600 border border-rose-300 text-white rounded-sm';
                                        el.innerText = '✕';
                                    } else if (hasMiss) {
                                        el.className = 'cell bg-slate-800 border border-slate-700 text-slate-500 rounded-sm';
                                        el.innerText = '•';
                                    } else if (isShip) {
                                        el.className = 'cell bg-cyan-600/90 border border-cyan-400 text-white rounded-sm shadow-[0_0_8px_rgba(6,182,212,0.3)] animate-pulse';
                                        el.innerText = '';
                                    } else {
                                        el.className = 'cell bg-slate-800/40 hover:bg-slate-800/80 border border-slate-800 rounded-sm';
                                        el.innerText = '';
                                    }
                                }
                            }
                        }
                    }

                    if (data.bot_shot) {
                        handleBotShotResult(data.bot_shot);
                    } else if (itemId === 'combat_airstrike') {
                        log(`Xác nhận: Bot đối phương bị tê liệt hỏa lực và MẤT LƯỢT PHẢN CÔNG!`, 'text-emerald-400 font-bold');
                        if (phase === 'playing') startTurnTimer(true);
                    }
                } else {
                    alert(data.error || 'Lỗi khi kích hoạt kỹ năng!');
                }
            } catch (err) {
                console.error(err);
                alert('Mất kết nối máy chủ tác chiến!');
            }
        }

        /* --- XỬ LÝ SHOP --- */
        function updateRestockCountdown() {
            const now = new Date();
            const midnight = new Date();
            midnight.setHours(24, 0, 0, 0);

            const diff = midnight - now;
            if (diff <= 0) {
                document.getElementById('restockTimer').innerText = '00:00:00 (Đang Restock...)';
                return;
            }

            const hrs = String(Math.floor(diff / (1000 * 60 * 60))).padStart(2, '0');
            const mins = String(Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
            const secs = String(Math.floor((diff % (1000 * 60)) / 1000)).padStart(2, '0');

            const timerEl = document.getElementById('restockTimer');
            if (timerEl) timerEl.innerText = `${hrs}:${mins}:${secs}`;
        }
        setInterval(updateRestockCountdown, 1000);
        updateRestockCountdown();

        function switchShopTab(tab) {
            currentShopTab = tab;
            const btnDaily = document.getElementById('tabDailyBtn');
            const btnGems = document.getElementById('tabGemsBtn');

            if (tab === 'daily') {
                btnDaily.className = 'px-4 py-2 border-b-2 border-amber-400 text-amber-300 transition';
                btnGems.className = 'px-4 py-2 border-b-2 border-transparent text-slate-400 hover:text-fuchsia-300 transition flex items-center gap-1';
            } else {
                btnDaily.className = 'px-4 py-2 border-b-2 border-transparent text-slate-400 hover:text-amber-300 transition';
                btnGems.className = 'px-4 py-2 border-b-2 border-fuchsia-400 text-fuchsia-300 transition flex items-center gap-1';
            }

            playSFX('click');
            renderShopCatalog();
        }

        function openShopModal() {
            if (!currentUser) {
                openModal('loginModal');
                return;
            }
            renderShopCatalog();
            openModal('shopModal');
        }

        function renderShopCatalog() {
            const container = document.getElementById('shopTabContent');
            container.innerHTML = '';

            const today = new Date().toISOString().split('T')[0];
            const daily = (currentUser?.daily_purchases?.date === today) ? (currentUser.daily_purchases.items || {}) : {};

            const categories = [
                { key: 'recon', title: '📡 HỆ THỐNG TRINH SÁT (RECON MODULES)', border: 'border-cyan-950', text: 'text-cyan-400' },
                { key: 'combat', title: '🚀 HỎA LỰC TÁC CHIẾN (OFFENSIVE MODULES)', border: 'border-rose-950', text: 'text-rose-400' },
                { key: 'defend', title: '🛡️ PHÒNG THỦ & ĐIỀU ĐỘNG (DEFENSIVE MODULES)', border: 'border-emerald-950', text: 'text-emerald-400' }
            ];

            categories.forEach(cat => {
                const catBlock = document.createElement('div');
                catBlock.innerHTML = `
                    <div class="flex items-center gap-2 mb-2 pb-1 border-b ${cat.border}">
                        <span class="text-xs font-bold uppercase ${cat.text} tracking-wider">${cat.title}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3" id="cat-grid-${cat.key}"></div>
                `;
                container.appendChild(catBlock);

                const grid = catBlock.querySelector(`#cat-grid-${cat.key}`);

                for (let [itemId, spec] of Object.entries(SHOP_ITEMS_SPEC)) {
                    if (spec.cat !== cat.key) continue;

                    const boughtToday = daily[itemId] || 0;
                    const remainingToday = Math.max(0, spec.limit - boughtToday);
                    const isOutOfStock = (currentShopTab === 'daily' && remainingToday <= 0);

                    const card = document.createElement('div');
                    card.className = `bg-slate-900/60 border ${isOutOfStock ? 'border-slate-800 opacity-60' : 'border-slate-800 hover:border-slate-700'} p-3 rounded-lg flex flex-col justify-between transition`;

                    let priceBadge = '';
                    let actionButton = '';

                    if (currentShopTab === 'daily') {
                        priceBadge = `<span class="text-xs font-bold text-amber-400 font-mono-tactical">$${spec.price}</span>`;
                        if (isOutOfStock) {
                            actionButton = `<button disabled class="w-full py-1.5 bg-slate-800 border border-slate-700 text-slate-500 rounded text-xs uppercase font-bold tracking-wider cursor-not-allowed">HẾT HÀNG (0/${spec.limit})</button>`;
                        } else {
                            actionButton = `<button onclick="buyPowerup('${itemId}')" class="w-full py-1.5 border border-amber-500/40 hover:bg-amber-500/10 text-amber-300 rounded text-xs uppercase font-bold tracking-wider transition">MUA ($${spec.price}) [CÒN ${remainingToday}/${spec.limit}]</button>`;
                        }
                    } else {
                        priceBadge = `<span class="text-xs font-bold text-fuchsia-400 font-mono-tactical">💎${spec.gemPrice}</span>`;
                        actionButton = `<button onclick="buyWithGems('${itemId}')" class="w-full py-1.5 bg-fuchsia-950/60 hover:bg-fuchsia-900/80 border border-fuchsia-500/50 text-fuchsia-200 rounded text-xs uppercase font-bold tracking-wider transition shadow-[0_0_10px_rgba(192,132,252,0.2)]">MUA BẰNG 💎${spec.gemPrice} (VÔ HẠN)</button>`;
                    }

                    card.innerHTML = `
                        <div>
                            <div class="flex justify-between items-start mb-1.5">
                                <span class="text-xs font-bold text-slate-200">${spec.name}</span>
                                ${priceBadge}
                            </div>
                            <p class="text-[11px] text-slate-400 leading-relaxed mb-3">${spec.desc}</p>
                        </div>
                        <div>${actionButton}</div>
                    `;
                    grid.appendChild(card);
                }
            });
        }

        async function buyPowerup(itemId) {
            const res = await fetch('/api/shop/buy', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ item_id: itemId })
            });

            const data = await res.json();
            if (res.ok) {
                currentUser.credits = data.credits;
                currentUser.gems = data.gems;
                currentUser.inventory = data.inventory;
                currentUser.daily_purchases = data.daily_purchases;
                playSFX('click');
                renderUserHUD();
                renderShopCatalog();
                log(data.message, 'text-yellow-300 font-bold');
            } else {
                alert(data.error);
            }
        }

        async function buyWithGems(itemId) {
            const res = await fetch('/api/shop/buy-gem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ item_id: itemId })
            });

            const data = await res.json();
            if (res.ok) {
                currentUser.credits = data.credits;
                currentUser.gems = data.gems;
                currentUser.inventory = data.inventory;
                playSFX('click');
                renderUserHUD();
                renderShopCatalog();
                log(data.message, 'text-fuchsia-300 font-bold');
            } else {
                alert(data.error);
            }
        }

        async function resetShopRestock() {
            if (!confirm('Bạn có chắc chắn muốn dùng 25 Gems để làm mới toàn bộ số lượng mua hàng hôm nay không?')) return;

            const res = await fetch('/api/shop/reset-restock', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });

            const data = await res.json();
            if (res.ok) {
                currentUser.gems = data.gems;
                currentUser.credits = data.credits;
                currentUser.daily_purchases = data.daily_purchases;
                playSFX('victory');
                renderUserHUD();
                renderShopCatalog();
                log(data.message, 'text-emerald-400 font-bold');
            } else {
                alert(data.error);
            }
        }

        /* --- QUẢN LÝ THÀNH TỰU (ACHIEVEMENTS SYSTEM) --- */
        let cachedAchievements = [];
        let currentProfileTab = 'stats';

        async function loadAchievements() {
            if (!currentUser) return;
            try {
                const res = await fetch('/api/achievements');
                const data = await res.json();
                if (data.achievements) {
                    cachedAchievements = data.achievements;
                }
            } catch (err) {
                console.error('Không thể tải thành tựu:', err);
            }
        }

        function switchProfileTab(tab) {
            currentProfileTab = tab;
            const btnStats = document.getElementById('tabProfStatsBtn');
            const btnPve = document.getElementById('tabProfAchPveBtn');
            const btnPvp = document.getElementById('tabProfAchPvpBtn');

            const statsContent = document.getElementById('profTabStatsContent');
            const achContent = document.getElementById('profTabAchContent');

            [btnStats, btnPve, btnPvp].forEach(b => {
                b.className = 'px-3 py-2 border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition';
            });

            playSFX('click');

            if (tab === 'stats') {
                btnStats.className = 'px-3 py-2 border-b-2 border-cyan-400 text-cyan-300 transition';
                statsContent.classList.remove('hidden');
                achContent.classList.add('hidden');
            } else {
                if (tab === 'pve') {
                    btnPve.className = 'px-3 py-2 border-b-2 border-amber-400 text-amber-300 transition flex items-center gap-1.5';
                } else {
                    btnPvp.className = 'px-3 py-2 border-b-2 border-fuchsia-400 text-fuchsia-300 transition flex items-center gap-1.5';
                }
                statsContent.classList.add('hidden');
                achContent.classList.remove('hidden');
                renderAchievementList(tab);
            }
        }

        function renderAchievementList(category) {
            const container = document.getElementById('profTabAchContent');
            container.innerHTML = '';

            const list = cachedAchievements.filter(a => a.category === category);
            if (list.length === 0) {
                container.innerHTML = '<div class="text-slate-400 italic text-center py-6 text-sm">Đang tải dữ liệu quân công...</div>';
                return;
            }

            list.forEach(ach => {
                const card = document.createElement('div');
                const isCompleted = ach.completed;
                const isClaimed = ach.claimed;

                const diffBadge = {
                    easy: '<span class="text-[10px] px-2 py-0.5 rounded bg-emerald-950 border border-emerald-500/40 text-emerald-300 font-bold">DỄ</span>',
                    medium: '<span class="text-[10px] px-2 py-0.5 rounded bg-cyan-950 border border-cyan-500/40 text-cyan-300 font-bold">VỪA</span>',
                    hard: '<span class="text-[10px] px-2 py-0.5 rounded bg-amber-950 border border-amber-500/40 text-amber-300 font-bold">KHÓ</span>',
                    nightmare: '<span class="text-[10px] px-2 py-0.5 rounded bg-rose-950 border border-rose-500/40 text-rose-300 font-bold animate-pulse">CỰC KHÓ</span>'
                }[ach.difficulty] || '';

                card.className = `p-3.5 rounded-xl border transition flex items-center justify-between gap-4 ${
                    isClaimed ? 'bg-slate-950/40 border-slate-800 opacity-60' :
                    isCompleted ? 'bg-slate-950 border-amber-500/60 shadow-[0_0_15px_rgba(245,158,11,0.15)]' :
                    'bg-slate-950/80 border-slate-800/80'
                }`;

                let actionBtn = '';
                if (isClaimed) {
                    actionBtn = '<span class="text-xs font-mono-tactical text-slate-500 font-bold px-2.5 py-1.5 bg-slate-900 rounded border border-slate-800">✓ ĐÃ NHẬN</span>';
                } else if (isCompleted) {
                    actionBtn = `<button onclick="claimAchievement('${ach.code}')" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase rounded-lg transition shadow-[0_0_12px_rgba(245,158,11,0.4)] animate-bounce font-mono-tactical">NHẬN THƯỞNG</button>`;
                } else {
                    actionBtn = '<span class="text-xs font-mono-tactical text-slate-500 italic">Chưa đạt</span>';
                }

                card.innerHTML = `
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xl ${isCompleted ? 'bg-amber-500/20 border border-amber-500/40 text-amber-300' : 'bg-slate-900 border border-slate-800 text-slate-600'}">
                            ${isCompleted ? '🎖️' : '🔒'}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-bold text-slate-100 ${isCompleted ? 'text-amber-300' : ''}">${ach.title}</span>
                                ${diffBadge}
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">${ach.description}</p>
                            <div class="flex items-center gap-3 mt-1.5 font-mono-tactical text-xs">
                                <span class="text-amber-400 font-extrabold">+$${ach.reward_credits}</span>
                                <span class="text-fuchsia-400 font-extrabold">+${ach.reward_gems} 💎</span>
                            </div>
                        </div>
                    </div>
                    <div>${actionBtn}</div>
                `;
                container.appendChild(card);
            });
        }

        async function claimAchievement(code) {
            try {
                const res = await fetch('/api/achievements/claim', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();
                if (res.ok) {
                    currentUser.credits = data.total_credits;
                    currentUser.gems = data.total_gems;
                    renderUserHUD();

                    playSFX('victory');
                    log(`[QUÂN CÔNG] ${data.message}`, 'text-amber-300 font-bold');
                    triggerSkillAlert(data.message, false);

                    await loadAchievements();
                    renderAchievementList(currentProfileTab);
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const errBox = document.getElementById('loginError');
            errBox.classList.add('hidden');

            const res = await fetch('/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();
            if (res.ok) {
                currentUser = data.user;
                playSFX('click');
                renderUserHUD();
                closeModal('loginModal');
                loadAchievements();
                log(`Chào mừng Chỉ Huy [${currentUser.name}] trở lại tổng hành dinh!`, 'text-emerald-400');
            } else {
                errBox.innerText = data.error || 'Đăng nhập thất bại!';
                errBox.classList.remove('hidden');
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            const name = document.getElementById('regName').value;
            const email = document.getElementById('regEmail').value;
            const password = document.getElementById('regPassword').value;
            const errBox = document.getElementById('regError');
            errBox.classList.add('hidden');

            const res = await fetch('/auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ name, email, password })
            });
            const data = await res.json();
            if (res.ok) {
                currentUser = data.user;
                playSFX('victory');
                renderUserHUD();
                closeModal('registerModal');
                loadAchievements();
                log(`Quân tịch hợp lệ! Chào mừng Chỉ Huy [${currentUser.name}] (+100$ & +30💎)!`, 'text-yellow-400');
            } else {
                errBox.innerText = data.message || 'Đăng ký không thành công!';
                errBox.classList.remove('hidden');
            }
        }

        async function logout() {
            await fetch('/auth/logout', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            currentUser = null;
            document.getElementById('authGuestBlock').classList.remove('hidden');
            document.getElementById('authUserBlock').classList.add('hidden');
            renderPlayerSkills();
            log('Đã đăng xuất khỏi hệ thống chỉ huy.', 'text-slate-500');
        }

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
                    btn.onclick = () => { selectedShipIndex = idx; renderShipButtons(); playSFX('click'); };
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
                        cell.classList.add('cursor-pointer', 'hover:border-rose-500/60');
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
            if (phase === 'playing' && (targetingSkill === 'def_repair_relocate' || targetingSkill === 'def_tactical_relocate')) {
                const clickedShip = placedShips.find(s => s.coordinates.some(c => c.x === x && c.y === y));
                if (clickedShip) {
                    sendSkillExecution(targetingSkill, { target_ship_name: clickedShip.name });
                } else {
                    alert('Vui lòng nhấp trúng vào một chiếc tàu trên bàn cờ của bạn!');
                }
                return;
            }

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

            playSFX('click');

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
            targetingSkill = null;

            clearInterval(turnTimerInterval);
            document.getElementById('turnTimerContainer').classList.add('hidden');

            const botGrid = document.getElementById('botGrid');
            botGrid.classList.add('opacity-40', 'pointer-events-none');
            botGrid.classList.remove('border-rose-600/70', 'shadow-[0_0_25px_rgba(244,63,94,0.2)]');
            document.getElementById('placementControls').classList.remove('hidden');
            document.getElementById('gameStatusText').innerText = "HỆ THỐNG DÀN TRẬN: Hãy đặt đủ 5 tàu chiến lên bàn cờ để kích hoạt radar.";
            renderEmptyBoards();
            renderShipButtons();
            renderPlayerSkills();
            document.getElementById('botSkillsList').innerHTML = '<span class="text-[10px] text-slate-500 italic text-center">Chờ quét...</span>';
            log('Đã cài đặt lại toàn bộ hải đồ tác chiến.', 'text-slate-500');
        }

        async function confirmDeployment() {
            if (placedShips.length < 5) return;

            // NẾU ĐANG CHƠI PVP ONLINE
            if (gameMode === 'pvp' && currentRoomData) {
                const btnStart = document.getElementById('btnStartWar');
                btnStart.disabled = true;
                btnStart.innerText = 'ĐÃ SẴN SÀNG (ĐANG CHỜ ĐỐI THỦ...)';
                btnStart.className = 'bg-amber-600 text-white font-bold px-5 py-1.5 rounded-md text-xs uppercase tracking-wider transition shadow-[0_0_15px_rgba(245,158,11,0.4)] animate-pulse';

                const roomCode = currentPvpRoomCode || currentRoomData.room_code || currentRoomData.room?.room_code;
                const res = await fetch('/api/pvp/ready', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        room_code: roomCode,
                        ships: placedShips
                    })
                });

                const data = await res.json();
                if (data.is_started) {
                    onBothPlayersReady(data.room);
                } else {
                    log('Hạm đội đã triển khai! Đang đợi chỉ huy đối phương sẵn sàng...', 'text-amber-400 font-bold');
                }
                return;
            }

            // NẾU ĐANG CHƠI VỚI BOT (GIỮ NGUYÊN CODE CŨ)
            let selectedDifficulty = document.getElementById('difficultySelect').value;
            let actualDifficulty = selectedDifficulty;

            if (selectedDifficulty === 'random') {
                const levels = ['easy', 'medium', 'hard', 'nightmare'];
                actualDifficulty = levels[Math.floor(Math.random() * levels.length)];
            }

            const res = await fetch('/api/games', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ 
                    difficulty: actualDifficulty, 
                    mode: 'pve', 
                    player_ships: placedShips 
                })
            });

            const data = await res.json();
            currentGameId = data.game_id;
            phase = 'playing';
            startTurnTimer(true);

            playSFX('alarm');

            document.getElementById('placementControls').classList.add('hidden');
            const botGrid = document.getElementById('botGrid');
            botGrid.classList.remove('opacity-40', 'pointer-events-none');
            botGrid.classList.add('border-rose-600/70', 'shadow-[0_0_25px_rgba(244,63,94,0.2)]');
            
            document.getElementById('gameStatusText').innerText = "HỆ THỐNG RADAR KÍCH HOẠT: Chọn tọa độ trên vùng biển đối phương để khai hỏa!";
            renderBotLoadoutSlots(data.bot_items_count || 3);

            if (selectedDifficulty === 'random') {
                log(`Đã kích hoạt chế độ NGẪU NHIÊN BÍ MẬT! Đối thủ mang cấp độ chưa rõ.`, 'text-amber-400 font-bold');
            } else {
                log(`Hải đồ thiết lập hoàn tất. Cấp độ tác chiến: ${actualDifficulty.toUpperCase()}`, 'text-emerald-400');
            }
        }

        async function fireAt(x, y) {
            console.log(`Bắn: x=${x}, y=${y}, phase=${phase}, mode=${gameMode}, roomCode=${currentPvpRoomCode}`);
            clearInterval(turnTimerInterval);
            if (phase !== 'playing') return;

            // XỬ LÝ KHAI HỎA TRONG TRẬN PVP ONLINE
            if (gameMode === 'pvp') {
                const roomCode = currentPvpRoomCode || currentRoomData?.room_code || currentRoomData?.room?.room_code;
                if (!roomCode) {
                    console.error("Không tìm thấy mã phòng PvP!");
                    alert('Lỗi: Không tìm thấy mã phòng tác chiến!');
                    return;
                }

                const targetCell = document.getElementById(`b-${x}-${y}`);
                if (targetCell && targetCell.dataset.fired === "true") {
                    console.log("Ô này đã bắn rồi!");
                    return;
                }

                playSFX('fire');

                try {
                    const res = await fetch('/api/pvp/fire', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ room_code: roomCode, x: x, y: y })
                    });

                    const data = await res.json();
                    console.log("Kết quả bắn từ server:", data);

                    if (!res.ok) {
                        alert(data.error || 'Lỗi bắn đạn!');
                        if (phase === 'playing') {
                            startTurnTimer(true);
                        }
                        return;
                    }

                    if (data.shot) {
                        handlePvpShotResult(data.shot);
                    }
                } catch (err) {
                    console.error("Lỗi gửi phát bắn:", err);
                }
                return;
            }

            // XỬ LÝ KHI ĐÁNH BOT (PVE)
            if (!currentGameId) return;

            if (targetingSkill === 'recon_scan' || targetingSkill === 'recon_sonar') {
                sendSkillExecution(targetingSkill, { target_x: x, target_y: y });
                return;
            }

            const targetCell = document.getElementById(`b-${x}-${y}`);
            if (targetCell.dataset.fired) return;
            targetCell.dataset.fired = "true";

            playSFX('fire');

            const res = await fetch(`/api/games/${currentGameId}/fire`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ x, y })
            });

            const data = await res.json();
            if (data.error) return;

            const shot = data.player_shot;
            const coordStr = toCoordName(x, y);
            const resultToShow = shot.display_result || shot.result;

            if (resultToShow === 'smoke_hidden') {
                targetCell.className = 'cell bg-purple-950/80 border border-purple-500/60 text-purple-300 font-bold rounded-sm shadow-[0_0_10px_rgba(168,85,247,0.3)] animate-pulse';
                targetCell.innerText = '💨';
                log(`[MÀN KHÓI] Hỏa lực bắn vào [${coordStr}] bị khói mù che khuất! Không thể xác định trúng hay trượt!`, 'text-purple-400 font-bold');
            } else if (resultToShow === 'hit' || resultToShow === 'sunk') {
                if (resultToShow === 'sunk') playSFX('sunk');
                else playSFX('hit');

                targetCell.className = 'cell bg-rose-600 border border-rose-400 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-pulse';
                targetCell.innerText = '✕';
                log(`HỎA LỰC TRÚNG MỤC TIÊU tại [${coordStr}]! ${resultToShow === 'sunk' ? 'XÁC NHẬN TÀU ĐỐI PHƯƠNG ĐÃ CHÌM!' : ''}`, 'text-emerald-400 font-bold');
                
                if (data.game_status === 'playing') {
                    startTurnTimer(true);
                }
            } else if (resultToShow === 'shield_blocked') {
                playSFX('shield');
                targetCell.className = 'cell bg-amber-500 border border-amber-300 text-black rounded-sm';
                targetCell.innerText = '🛡️';
                log(`ĐẠN BỊ CHẶN! Khiên năng lượng của Bot đã vô hiệu hóa phát đạn tại [${coordStr}]!`, 'text-amber-400 font-bold');
            } else {
                playSFX('miss');
                targetCell.className = 'cell bg-slate-800/80 border border-slate-700 text-slate-500 rounded-sm';
                targetCell.innerText = '•';
                log(`Hỏa lực trượt tại [${coordStr}].`, 'text-slate-400');
            }

            if (data.revealed_smoke_cells && data.revealed_smoke_cells.length > 0) {
                setTimeout(() => {
                    log(`[MÀN KHÓI TAN BIẾN] Gió biển đã thổi tan sương mù! Toàn bộ vị trí hỏa lực lộ diện!`, 'text-cyan-300 font-bold');
                    triggerSkillAlert("MÀN KHÓI ĐỐI PHƯƠNG ĐÃ TAN BIẾN!", false);

                    data.revealed_smoke_cells.forEach(c => {
                        const cell = document.getElementById(`b-${c.x}-${c.y}`);
                        if (!cell) return;

                        const coordName = toCoordName(c.x, c.y);
                        if (c.result === 'hit' || c.result === 'sunk') {
                            cell.className = 'cell bg-rose-600 border border-rose-400 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-bounce';
                            cell.innerText = '✕';
                            log(`Vết đạn tại [${coordName}] lộ diện: ĐÃ BẮN TRÚNG TÀU ĐỐI PHƯƠNG!`, 'text-emerald-400 font-bold');
                        } else {
                            cell.className = 'cell bg-slate-800/80 border border-slate-700 text-slate-500 rounded-sm';
                            cell.innerText = '•';
                        }
                    });
                }, 700);
            }

            if (data.unlocked_achievements && data.unlocked_achievements.length > 0) {
                data.unlocked_achievements.forEach(achTitle => {
                    playSFX('victory');
                    log(`🎖️ QUÂN CÔNG: Mở khóa thành tựu [${achTitle}]! Mở Hồ Sơ nhận thưởng!`, 'text-amber-300 font-bold');
                    triggerSkillAlert(`THÀNH TỰU MỚI: ${achTitle.toUpperCase()}`, false);
                });
                loadAchievements();
            }

            if (data.game_status === 'won') {
                phase = 'ended';
                clearInterval(turnTimerInterval);
                document.getElementById('turnTimerContainer').classList.add('hidden');
                playSFX('victory');
                document.getElementById('gameStatusText').innerText = "CHIẾN THẮNG CHUNG CUỘC! TOÀN BỘ HẠM ĐỘI ĐỐI PHƯƠNG ĐÃ BỊ TIÊU DIỆT!";
                if (currentUser) {
                    currentUser.stats = data.user_stats;
                    currentUser.credits = data.total_credits;
                    currentUser.gems = data.total_gems;
                    renderUserHUD();
                }
                if (data.stats) showVictoryModal(data.stats, data.earned_credits, data.earned_gems);
                return;
            }

            if (data.bot_used_power) {
                const p = data.bot_used_power;
                revealBotPowerup(p.item);
                triggerSkillAlert(p.msg, true);
                log(`[BOT KÍCH HOẠT] ${p.msg}`, 'text-rose-400 font-extrabold');
                handleBotVisualEffects(p.effect);
            }

            if (data.bot_shot) {
                handleBotShotResult(data.bot_shot);
            }

            if (data.bot_extra_shot) {
                setTimeout(() => {
                    log(`CẢNH BÁO: Bạn bị Không Kích làm MẤT LƯỢT! Bot nã tiếp phát pháo thứ hai!`, 'text-rose-500 font-bold');
                    handleBotShotResult(data.bot_extra_shot);
                }, 750);
            }

            if (data.game_status === 'lost') {
                phase = 'ended';
                clearInterval(turnTimerInterval);
                document.getElementById('turnTimerContainer').classList.add('hidden');
                document.getElementById('gameStatusText').innerText = "THẤT BẠI TÁC CHIẾN! HẠM ĐỘI CỦA BẠN ĐÃ BỊ TIÊU DIỆT HOÀN TOÀN!";
            }
        }

        function handleBotShotResult(bShot) {
            const pCell = document.getElementById(`p-${bShot.x}-${bShot.y}`);
            const bCoordStr = toCoordName(bShot.x, bShot.y);

            setTimeout(() => {
                if (bShot.result === 'hit' || bShot.result === 'sunk') {
                    if (bShot.result === 'sunk') playSFX('sunk');
                    else playSFX('hit');

                    pCell.className = 'cell bg-rose-600 border border-rose-300 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-bounce';
                    pCell.innerText = '✕';
                    log(`CẢNH BÁO: Tàu của ta trúng đạn tại [${bCoordStr}]!`, 'text-rose-400 font-bold');
                } else if (bShot.result === 'player_shield_blocked') {
                    playSFX('shield');
                    pCell.className = 'cell bg-cyan-500 border border-cyan-200 text-black font-black rounded-sm';
                    pCell.innerText = '🛡️';
                    log(`KHIÊN NĂNG LƯỢNG của ta đã chặn đứng phát đạn của Bot tại [${bCoordStr}]!`, 'text-cyan-300 font-bold');
                } else {
                    playSFX('miss');
                    pCell.className = 'cell bg-slate-800 border border-slate-700 text-slate-500 rounded-sm';
                    pCell.innerText = '•';
                    log(`Đối phương bắn trượt tại [${bCoordStr}].`, 'text-slate-500');
                }

                if (phase === 'playing') {
                    startTurnTimer(true);
                }
            }, 350);
        }

        renderEmptyBoards();
        renderShipButtons();

        async function loadLeaderboard() {
            let diff = document.getElementById('difficultySelect').value;
            if (diff === 'random') diff = 'medium';

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

        document.getElementById('difficultySelect').addEventListener('change', loadLeaderboard);

        function showVictoryModal(stats, earnedCredits, earnedGems) {
            lastGameStats = stats;
            document.getElementById('resScore').innerText = stats.score.toLocaleString();
            document.getElementById('resTime').innerText = `${stats.duration_seconds}s`;
            document.getElementById('resAccuracy').innerText = `${stats.accuracy}%`;
            document.getElementById('resFleetHp').innerText = `${stats.fleet_health}%`;
            
            document.getElementById('rewardCredits').innerText = `+$${earnedCredits}`;
            document.getElementById('rewardGems').innerText = `+${earnedGems} 💎`;

            if (currentUser) {
                document.getElementById('commanderName').value = currentUser.name;
            }
            document.getElementById('victoryModal').classList.remove('hidden');
        }

        async function submitScore() {
            const name = document.getElementById('commanderName').value;
            const res = await fetch(`/api/games/${currentGameId}/save-score`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ player_name: name })
            });
            const data = await res.json();

            if (currentUser) {
                currentUser.credits = data.total_credits;
                renderUserHUD();
            }

            document.getElementById('victoryModal').classList.add('hidden');
            loadLeaderboard();
        }

        checkCurrentUser();
        loadLeaderboard();

        /* ===================================================
           QUẢN LÝ PHÒNG ĐẤU PVP ONLINE & WEBSOCKET REVERB
           =================================================== */
        let currentRoomData = null;
        let pvpEchoChannel = null;

        function openPvpModal() {
            if (!currentUser) {
                openModal('loginModal');
                return;
            }
            document.getElementById('pvpMenuSection').classList.remove('hidden');
            document.getElementById('pvpWaitingSection').classList.add('hidden');
            document.getElementById('inputRoomCode').value = '';
            openModal('pvpModal');
        }

        // Khởi tạo kết nối Pusher/Reverb client chuẩn xác
        const pusherClient = new Pusher("{{ env('REVERB_APP_KEY') }}", {
            cluster: 'mt1',
            wsHost: window.location.hostname,
            wsPort: {{ env('REVERB_PORT', 8080) }},
            wssPort: {{ env('REVERB_PORT', 8080) }},
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
        });

        function subscribeToRoom(roomCode) {
            if (pvpEchoChannel) {
                pusherClient.unsubscribe('room.' + roomCode);
            }

            pvpEchoChannel = pusherClient.subscribe('room.' + roomCode);

            pvpEchoChannel.bind('player.joined', function(data) {
                handlePlayerJoined(data);
            });
            pvpEchoChannel.bind('.player.joined', function(data) {
                handlePlayerJoined(data);
            });

            // LẮNG NGHE KHI CẢ 2 ĐÃ BẤM SẴN SÀNG
            pvpEchoChannel.bind('pvp.game.started', function(data) {
                onBothPlayersReady(data.room);
            });
            pvpEchoChannel.bind('.pvp.game.started', function(data) {
                onBothPlayersReady(data.room);
            });

            // LẮNG NGHE KHI CÓ PHÁT BẮN PVP
            pvpEchoChannel.bind('pvp.shot.fired', function(data) {
                handlePvpShotResult(data.shotData);
            });
            pvpEchoChannel.bind('.pvp.shot.fired', function(data) {
                handlePvpShotResult(data.shotData);
            });

            // LẮNG NGHE KHI ĐỐI PHƯƠNG SỬ DỤNG KỸ NĂNG
            pvpEchoChannel.bind('pvp.skill.used', function(data) {
                handlePvpSkillEffect(data.skillData);
            });
            pvpEchoChannel.bind('.pvp.skill.used', function(data) {
                handlePvpSkillEffect(data.skillData);
            });
        }

        function handlePlayerJoined(data) {
            if (!data || !data.room) return;
            log(`[PVP ONLINE] Đối thủ đã kết nối vào phòng! Bắt đầu trận chiến!`, 'text-emerald-400 font-bold');
            playSFX('victory');
            closeModal('pvpModal');
            startPvpMatch(data.room, 'player1');
        }

        async function createPvpRoom() {
            try {
                myPvpRole = 'player1';
                const res = await fetch('/api/pvp/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await res.json();
                if (res.ok) {
                    currentRoomData = data;
                    currentPvpRoomCode = data.room_code;

                    document.getElementById('pvpMenuSection').classList.add('hidden');
                    document.getElementById('pvpWaitingSection').classList.remove('hidden');
                    document.getElementById('displayRoomCode').innerText = data.room_code;
                    
                    subscribeToRoom(data.room_code);

                    playSFX('click');
                    log(`Đã tạo phòng PvP thành công: [${data.room_code}]. Đang đợi đối thủ tham gia...`, 'text-indigo-400 font-bold');
                } else {
                    alert(data.error || 'Lỗi tạo phòng!');
                }
            } catch (err) {
                console.error(err);
                alert('Không thể kết nối máy chủ PvP!');
            }
        }

        async function joinPvpRoom() {
            const roomCode = document.getElementById('inputRoomCode').value.trim().toUpperCase();
            if (!roomCode) {
                alert('Vui lòng nhập mã phòng chiến!');
                return;
            }

            currentPvpRoomCode = roomCode;

            try {
                const res = await fetch('/api/pvp/join', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ room_code: roomCode })
                });
                const data = await res.json();
                if (res.ok) {
                    currentRoomData = data;
                    closeModal('pvpModal');
                    playSFX('victory');
                    log(`Đã gia nhập thành công phòng [${roomCode}]!`, 'text-emerald-400 font-bold');

                    subscribeToRoom(roomCode);
                    startPvpMatch(data.room, 'player2');
                } else {
                    alert(data.error || 'Không thể vào phòng!');
                }
            } catch (err) {
                console.error(err);
                alert('Lỗi khi tham gia phòng đấu!');
            }
        }

        function onBothPlayersReady(room) {
            phase = 'playing';
            gameMode = 'pvp'; 
            currentRoomData = room;
            playSFX('alarm');

            if (room && room.room_code) currentPvpRoomCode = room.room_code;

            // Ẩn thanh công cụ dàn trận
            document.getElementById('placementControls').classList.add('hidden');

            const isMyTurn = (myPvpRole === room.current_turn);
            const botGrid = document.getElementById('botGrid');

            // Gỡ bỏ hoàn toàn trạng thái mờ và khóa tương tác
            botGrid.classList.remove('opacity-40', 'pointer-events-none');
            botGrid.classList.add('border-rose-600/70', 'shadow-[0_0_25px_rgba(244,63,94,0.2)]');

            // Gán lại onclick và cursor cho toàn bộ 100 ô đối phương để nhận click
            for (let y = 0; y < 10; y++) {
                for (let x = 0; x < 10; x++) {
                    const c = document.getElementById(`b-${x}-${y}`);
                    if (c) {
                        c.onclick = () => fireAt(x, y);
                        c.classList.add('cursor-pointer', 'hover:border-rose-500/60');
                    }
                }
            }

            if (isMyTurn) {
                botGrid.classList.remove('pointer-events-none');
                document.getElementById('gameStatusText').innerText = "TRẬN ĐẤU BẮT ĐẦU! LƯỢT CỦA BẠN: Khai hỏa vào hải đồ đối phương!";
            } else {
                botGrid.classList.add('pointer-events-none');
                document.getElementById('gameStatusText').innerText = "TRẬN ĐẤU BẮT ĐẦU! ĐỐI PHƯƠNG KHAI HỎA TRƯỚC, HÃY CHỜ ĐỢI...";
            }

            log(`Cả hai chỉ huy đã sẵn sàng! Trận chiến hải quân chính thức bùng nổ!`, 'text-emerald-400 font-extrabold text-sm');
            triggerSkillAlert("HAI BÊN ĐÃ SẴN SÀNG - KHAI HỎA!", false);

            startTurnTimer(isMyTurn);
        }

        function startPvpMatch(room, role = null) {
            gameMode = 'pvp';
            if (role) myPvpRole = role;
            phase = 'setup';
            resetSetup();
            
            gameMode = 'pvp';

            document.getElementById('gameStatusText').innerText = `TRẬN ĐẤU PVP ONLINE (${room.room_code}): Hãy dàn trận và bấm VÀO TRẬN để SẴN SÀNG!`;
            triggerSkillAlert(`ĐỐI THỦ ĐÃ VÀO PHÒNG - HÃY DÀN TRẬN!`, false);
        }

        function handlePvpShotResult(shot) {
            console.log("Xử lý kết quả bắn:", shot);
            const isMeShooting = (shot.shooter_role === myPvpRole);
            const coordStr = toCoordName(shot.x, shot.y);

            if (isMeShooting) {
                // Mình là người bắn -> Đánh dấu lên bàn cờ đối phương (bên phải)
                const targetCell = document.getElementById(`b-${shot.x}-${shot.y}`);
                if (targetCell) {
                    targetCell.dataset.fired = "true";
                    if (shot.result === 'hit' || shot.result === 'sunk') {
                        if (shot.result === 'sunk') playSFX('sunk');
                        else playSFX('hit');
                        targetCell.className = 'cell bg-rose-600 border border-rose-400 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-pulse';
                        targetCell.innerText = '✕';
                        log(`[PVP] Bạn bắn TRÚNG tại [${coordStr}]! ${shot.result === 'sunk' ? 'ĐỐI THỦ BỊ CHÌM TÀU!' : 'Được bắn tiếp!'}`, 'text-emerald-400 font-bold');
                    } else {
                        playSFX('miss');
                        targetCell.className = 'cell bg-slate-800/80 border border-slate-700 text-slate-500 rounded-sm';
                        targetCell.innerText = '•';
                        log(`[PVP] Bạn bắn trượt tại [${coordStr}]. Chuyển lượt đối thủ!`, 'text-slate-400');
                    }
                }
            } else {
                // Đối phương bắn mình -> Đánh dấu lên bàn cờ của mình (bên trái)
                const myCell = document.getElementById(`p-${shot.x}-${shot.y}`);
                if (myCell) {
                    if (shot.result === 'hit' || shot.result === 'sunk') {
                        if (shot.result === 'sunk') playSFX('sunk');
                        else playSFX('hit');
                        myCell.className = 'cell bg-rose-600 border border-rose-300 text-white rounded-sm shadow-[0_0_12px_rgba(244,63,94,0.7)] animate-bounce';
                        myCell.innerText = '✕';
                        log(`[CẢNH BÁO] Đối phương bắn TRÚNG tàu của bạn tại [${coordStr}]!`, 'text-rose-400 font-bold');
                    } else {
                        playSFX('miss');
                        myCell.className = 'cell bg-slate-800 border border-slate-700 text-slate-500 rounded-sm';
                        myCell.innerText = '•';
                        log(`[PVP] Đối phương bắn trượt tại [${coordStr}]!`, 'text-slate-500');
                    }
                }
            }

            // Kiểm tra kết thúc trận
            if (shot.status === 'finished') {
                phase = 'ended';
                clearInterval(turnTimerInterval);
                document.getElementById('turnTimerContainer').classList.add('hidden');

                if (shot.winner === myPvpRole) {
                    playSFX('victory');
                    document.getElementById('gameStatusText').innerText = "CHIẾN THẮNG! BẠN ĐÃ BẮN HẠ TOÀN BỘ HẠM ĐỘI ĐỐI PHƯƠNG!";
                    log("[CHIẾN CÔNG PVP] Bạn đã giành chiến thắng chung cuộc!", "text-amber-300 font-black text-sm");
                    triggerSkillAlert("CHIẾN THẮNG HUY HOÀNG TRƯỚC ĐỐI THỦ!", false);
                } else {
                    document.getElementById('gameStatusText').innerText = "THẤT BẠI! TOÀN BỘ TÀU CỦA BẠN ĐÃ BỊ ĐỐI PHƯƠNG BẮN CHÌM!";
                    log("[THẤT BẠI PVP] Hạm đội của bạn đã bị tiêu diệt.", "text-rose-500 font-bold text-sm");
                }
                return;
            }

            // Cập nhật lượt
            const isMyTurnNow = (shot.next_turn === myPvpRole);
            const botGrid = document.getElementById('botGrid');

            if (isMyTurnNow) {
                botGrid.classList.remove('pointer-events-none', 'opacity-60');
                document.getElementById('gameStatusText').innerText = "LƯỢT CỦA BẠN: Khai hỏa vào hải đồ đối phương!";
            } else {
                botGrid.classList.add('pointer-events-none', 'opacity-60');
                document.getElementById('gameStatusText').innerText = "LƯỢT CỦA ĐỐI THỦ: Đang chờ đối thủ ngắm bắn...";
            }
            startTurnTimer(isMyTurnNow);
        }

        function handlePvpSkillEffect(effect) {
            if (!effect) return;
            const isMe = (effect.user_role === myPvpRole);

            log(`[KỸ NĂNG] ${effect.user_name}: ${effect.msg}`, isMe ? 'text-amber-300 font-bold' : 'text-rose-400 font-bold');
            triggerSkillAlert(effect.msg, !isMe);

            if (effect.type === 'recon_sat' && isMe && effect.target) {
                playSFX('sonar');
                const cell = document.getElementById(`b-${effect.target.x}-${effect.target.y}`);
                if (cell) {
                    cell.className = 'cell bg-amber-400 border border-amber-200 text-black font-black animate-ping rounded-sm';
                    cell.innerText = '🎯';
                }
            }

            if (effect.type === 'recon_scan') {
                playSFX('sonar');
                const prefix = isMe ? 'b' : 'p';
                for (let dx = -1; dx <= 1; dx++) {
                    for (let dy = -1; dy <= 1; dy++) {
                        const tx = effect.cx + dx;
                        const ty = effect.cy + dy;
                        const c = document.getElementById(`${prefix}-${tx}-${ty}`);
                        if (c && !c.dataset.fired) {
                            c.classList.add(isMe ? 'bg-cyan-500/50' : 'bg-rose-500/50', 'animate-pulse');
                            setTimeout(() => c.classList.remove('bg-cyan-500/50', 'bg-rose-500/50', 'animate-pulse'), 3000);
                        }
                    }
                }
            }

            if (effect.type === 'def_shield') {
                playSFX('shield');
                const grid = document.getElementById(isMe ? 'playerGrid' : 'botGrid');
                grid.classList.add('border-amber-400', 'shadow-[0_0_35px_rgba(251,191,36,0.6)]');
                setTimeout(() => {
                    grid.classList.remove('border-amber-400', 'shadow-[0_0_35px_rgba(251,191,36,0.6)]');
                }, 4000);
            }
        }
    </script>
</body>
</html>