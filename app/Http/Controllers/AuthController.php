<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:20|unique:users,name',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'credits'   => 100, // Tặng khởi nghiệp 100$
            'inventory' => [],
            'stats'     => [
                'easy'      => ['high_score' => 0, 'wins' => 0],
                'medium'    => ['high_score' => 0, 'wins' => 0],
                'hard'      => ['high_score' => 0, 'wins' => 0],
                'nightmare' => ['high_score' => 0, 'wins' => 0],
            ],
        ]);

        Auth::login($user);

        return response()->json([
            'status' => 'success',
            'user'   => $this->formatUserData($user),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            /** @var User $user */
            $user = Auth::user();

            return response()->json([
                'status' => 'success',
                'user'   => $this->formatUserData($user),
            ]);
        }

        return response()->json(['error' => 'Thông tin đăng nhập không chính xác!'], 401);
    }

    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'user'          => $this->formatUserData($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'success']);
    }

    protected function formatUserData(User $user): array
    {
        return [
            'name'      => $user->name,
            'email'     => $user->email,
            'credits'   => $user->credits ?? 0,
            'gems'      => $user->gems ?? 0,
            'inventory' => $user->inventory ?? [],
            'stats'     => $user->stats ?? [
                'easy'      => ['high_score' => 0, 'wins' => 0],
                'medium'    => ['high_score' => 0, 'wins' => 0],
                'hard'      => ['high_score' => 0, 'wins' => 0],
                'nightmare' => ['high_score' => 0, 'wins' => 0],
            ],
        ];
    }
}