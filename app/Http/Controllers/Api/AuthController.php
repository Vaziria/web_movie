<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Auth', 'Get a bearer token for the write endpoints. Only admin users can log in.', weight: 0)]
class AuthController extends Controller
{
    /**
     * Log in
     *
     * Exchange admin credentials for a bearer token. Send it as `Authorization: Bearer <token>`. Tokens do not
     * expire; revoke one with `POST /logout`. Wrong password or a non-admin user: 422. Limited to 5 attempts per
     * minute.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            /** @example admin@moviemov.id */
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            /**
             * Label for the token, e.g. the tool that uses it.
             *
             * @example uploader-bot
             */
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_admin) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $token = $user->createToken($data['device_name'] ?? 'api');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    /** Current user */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    /**
     * Log out
     *
     * Revokes the token used for this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
