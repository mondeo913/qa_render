<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::with('role')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        // Hash an unavailable identity as well, to avoid a fast identity-existence oracle.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$2rCvHuyPykQqPF9n9IBD5.EaC5QCLqD8X0a.t8O06q7s0KzW.jhQe');
        if (!$valid || !$user || $user->status !== 'ACTIVE' || !$user->role?->active) {
            throw ValidationException::withMessages(['email' => ['Credenciales incorrectas o acceso no habilitado.']]);
        }
        $expires = now()->addMinutes(max(1, min(1440, config('api.token_minutes'))));
        $token = $user->createToken($data['device_name'], ['siget:api', 'role:'.$user->role_id], $expires);
        $user->forceFill(['last_login_at' => now()])->save();
        app(\App\Services\AuditService::class)->record('api.token.issued', $user, [],
            ['device_name' => $data['device_name'], 'expires_at' => $expires->toIso8601String()], $request);

        return response()->json(['token_type' => 'Bearer', 'access_token' => $token->plainTextToken,
            'expires_at' => $expires->toIso8601String(), 'user' => ApiData::user($user)], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => ApiData::user($request->user())]);
    }

    public function destroy(Request $request)
    {
        app(\App\Services\AuditService::class)->record('api.token.revoked', $request->user(), [], [], $request);
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    public function destroyAll(Request $request)
    {
        app(\App\Services\AuditService::class)->record('api.tokens.revoked_all', $request->user(), [], [], $request);
        $request->user()->tokens()->delete();
        return response()->noContent();
    }
}
