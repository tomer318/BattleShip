<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AchievementController extends Controller
{
    /**
     * Lấy toàn bộ danh sách 30 thành tựu kèm trạng thái hoàn thành của user
     */
    public function index(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $achievements = DB::table('achievements')->orderBy('id')->get();
        $userProgress = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->pluck('claimed', 'achievement_code');

        $completedCodes = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->pluck('achievement_code')
            ->toArray();

        $result = $achievements->map(function ($ach) use ($completedCodes, $userProgress) {
            $isCompleted = in_array($ach->code, $completedCodes);
            $isClaimed = isset($userProgress[$ach->code]) && $userProgress[$ach->code];

            return [
                'code'           => $ach->code,
                'title'          => $ach->title,
                'description'    => $ach->description,
                'category'       => $ach->category,
                'difficulty'     => $ach->difficulty,
                'reward_credits' => $ach->reward_credits,
                'reward_gems'    => $ach->reward_gems,
                'completed'      => $isCompleted,
                'claimed'        => $isClaimed,
            ];
        });

        return response()->json([
            'achievements' => $result,
            'summary'      => [
                'total'     => count($achievements),
                'completed' => count($completedCodes),
            ]
        ]);
    }

    /**
     * Nhận phần thưởng Dollars ($) và Gems (💎) của thành tựu đã hoàn thành
     */
    public function claim(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập!'], 401);
        }

        $code = $request->input('code');
        $achievement = DB::table('achievements')->where('code', $code)->first();

        if (!$achievement) {
            return response()->json(['error' => 'Thành tựu không tồn tại!'], 404);
        }

        $userAch = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_code', $code)
            ->first();

        if (!$userAch || !$userAch->completed) {
            return response()->json(['error' => 'Bạn chưa hoàn thành mục tiêu này!'], 400);
        }

        if ($userAch->claimed) {
            return response()->json(['error' => 'Thành tựu này đã nhận thưởng trước đó!'], 400);
        }

        // Đánh dấu đã nhận & cộng tiền
        DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_code', $code)
            ->update(['claimed' => true, 'updated_at' => now()]);

        $user->credits = ($user->credits ?? 0) + $achievement->reward_credits;
        $user->gems = ($user->gems ?? 0) + $achievement->reward_gems;
        $user->save();

        return response()->json([
            'status'         => 'success',
            'reward_credits' => $achievement->reward_credits,
            'reward_gems'    => $achievement->reward_gems,
            'total_credits'  => $user->credits,
            'total_gems'     => $user->gems,
            'message'        => "Đã nhận thưởng thành tựu [{$achievement->title}]: +{$achievement->reward_credits}$ và +{$achievement->reward_gems}💎!",
        ]);
    }

    /**
     * Hàm helper dùng nội bộ để mở khóa thành tựu
     */
    public static function unlock(User $user, string $achievementCode): bool
    {
        $existing = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->where('achievement_code', $achievementCode)
            ->first();

        if ($existing && $existing->completed) {
            return false; // Đã mở khóa trước đó
        }

        DB::table('user_achievements')->updateOrInsert(
            ['user_id' => $user->id, 'achievement_code' => $achievementCode],
            [
                'completed'    => true,
                'completed_at' => now(),
                'updated_at'   => now(),
            ]
        );

        return true;
    }
}