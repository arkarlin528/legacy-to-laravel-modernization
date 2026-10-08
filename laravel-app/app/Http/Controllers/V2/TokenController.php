<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group v2 Authentication
 *
 * v2 replaces the shared static API key with a personal Sanctum token per user and device.
 */
class TokenController extends Controller
{
    /**
     * Issue an API token.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: admin@demo.test
     * @bodyParam password string required Example: secret-password
     * @bodyParam device_name string required A label for the token. Example: erp-integration
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }
}
