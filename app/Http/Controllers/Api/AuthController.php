<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login user toko optik dan kembalikan Bearer token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah. Silakan periksa kembali.',
            ], 401);
        }

        $token = $user->createApiToken($validated['device_name'] ?? 'arjuna-mayway-flutter');

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang di Optik Arjuna & May Way!',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user),
            ],
        ]);
    }

    /**
     * Registrasi pelanggan baru toko optik.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar. Silakan login.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => 'customer',
            'membership_tier' => 'Silver',
        ]);

        $token = $user->createApiToken($validated['device_name'] ?? 'arjuna-mayway-flutter');

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat. Selamat datang di Optik Arjuna & May Way!',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * Ambil data profil user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil dimuat.',
            'data' => [
                'user' => $this->formatUser($user),
            ],
        ]);
    }

    /**
     * Logout dan hapus token yang sedang aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var UserToken|null $tokenRecord */
        $tokenRecord = $request->attributes->get('current_api_token');

        if ($tokenRecord) {
            $tokenRecord->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    /**
     * Format data user toko optik untuk response API.
     *
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'membership_tier' => $user->membership_tier,
            'prescription_od' => $user->prescription_od,
            'prescription_os' => $user->prescription_os,
            'last_exam_date' => $user->last_exam_date?->format('Y-m-d'),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
