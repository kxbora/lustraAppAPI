<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function sendResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this email',
            ], 404);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => $expiresAt,
        ]);

        $defaultMailer = (string) config('mail.default');
        $smtpUsername = (string) config('mail.mailers.smtp.username');
        $smtpPassword = (string) config('mail.mailers.smtp.password');
        $isDebug = (bool) config('app.debug');

        if ($defaultMailer === 'smtp' && (empty($smtpUsername) || empty($smtpPassword))) {
            if ($isDebug) {
                return response()->json([
                    'message' => 'SMTP is not configured. OTP generated for local testing.',
                    'otp_code' => $otp,
                    'expires_at' => $expiresAt,
                ]);
            }

            return response()->json([
                'message' => 'Email delivery is not configured. Please configure MAIL_USERNAME and MAIL_PASSWORD.',
            ], 500);
        }

        try {
            Mail::raw(
                "Your LUSTRA password reset code is {$otp}. This code will expire in 10 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('LUSTRA Password Reset OTP');
                }
            );
        } catch (Throwable $e) {
            Log::error('Failed to send reset OTP email', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            if ($isDebug) {
                return response()->json([
                    'message' => 'Failed to send OTP email. OTP generated for local testing.',
                    'otp_code' => $otp,
                    'expires_at' => $expiresAt,
                ]);
            }

            return response()->json([
                'message' => 'Failed to send OTP email. Please check SMTP settings.',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP code sent to your email',
        ]);
    }

    public function verifyResetOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this email',
            ], 404);
        }

        if (! $user->otp_code || ! $user->otp_expires_at) {
            return response()->json([
                'message' => 'OTP code is not available. Please request a new code.',
            ], 422);
        }

        if ($user->otp_code !== $validated['otp_code']) {
            return response()->json([
                'message' => 'Invalid OTP code',
            ], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP code has expired. Please request a new code.',
            ], 422);
        }

        return response()->json([
            'message' => 'OTP verified',
        ]);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this email',
            ], 404);
        }

        if (! $user->otp_code || ! $user->otp_expires_at) {
            return response()->json([
                'message' => 'OTP code is not available. Please request a new code.',
            ], 422);
        }

        if ($user->otp_code !== $validated['otp_code']) {
            return response()->json([
                'message' => 'Invalid OTP code',
            ], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json([
                'message' => 'OTP code has expired. Please request a new code.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|string|max:10',
            'date_of_birth' => 'nullable|date',
            'profile_image' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'is_dark_mode' => 'nullable|boolean',
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function socialLogin(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|in:google,facebook',
            'access_token' => 'required|string',
        ]);

        try {
            $profile = $validated['provider'] === 'google'
                ? $this->googleProfile($validated['access_token'])
                : $this->facebookProfile($validated['access_token']);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }

        if (! isset($profile['email']) || empty($profile['email'])) {
            return response()->json([
                'message' => 'Unable to retrieve email from social account',
            ], 422);
        }

        $user = User::where('email', $profile['email'])->first();

        if (! $user && isset($profile['provider_id'])) {
            $user = User::where('social_provider', $validated['provider'])
                ->where('social_provider_id', $profile['provider_id'])
                ->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $profile['name'] ?? 'Social User',
                'email' => $profile['email'],
                'password' => Hash::make(Str::random(24)),
                'profile_image' => $profile['avatar'] ?? null,
                'social_provider' => $validated['provider'],
                'social_provider_id' => $profile['provider_id'] ?? null,
            ]);
        } else {
            $user->update([
                'name' => $profile['name'] ?? $user->name,
                'profile_image' => $profile['avatar'] ?? $user->profile_image,
                'social_provider' => $validated['provider'],
                'social_provider_id' => $profile['provider_id'] ?? $user->social_provider_id,
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Social login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function verifyPassword(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Incorrect password',
            ], 422);
        }

        return response()->json([
            'message' => 'Password verified',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'current_password' => 'required|string',
            'new_password' => 'nullable|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $updateData = [
            'name' => $validated['name'],
        ];

        if (! empty($validated['new_password'])) {
            $updateData['password'] = Hash::make($validated['new_password']);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    private function googleProfile(string $accessToken): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $accessToken,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Invalid Google token');
        }

        $data = $response->json();

        return [
            'provider_id' => $data['sub'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'avatar' => $data['picture'] ?? null,
        ];
    }

    private function facebookProfile(string $accessToken): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get('https://graph.facebook.com/me', [
                'fields' => 'id,name,email,picture',
                'access_token' => $accessToken,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Invalid Facebook token');
        }

        $data = $response->json();

        return [
            'provider_id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'avatar' => data_get($data, 'picture.data.url'),
        ];
    }
}

