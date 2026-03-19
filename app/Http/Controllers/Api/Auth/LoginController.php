<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Add this
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string', // Can be email or username
            'password' => 'required|string',
        ]);

        // Determine if login input is email or username
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $loginType => $request->login,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Create token with abilities based on role
            $token = $user->createToken('auth-token', [$user->role])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role' => $user->role,
                    ],
                    'token' => $token,
                    'redirect' => $this->getRedirectRoute($user->role),
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'The provided credentials are incorrect.'
        ], 401);
        
        // Or use ValidationException if you want field-specific errors:
        // throw ValidationException::withMessages([
        //     'login' => ['The provided credentials are incorrect.'],
        // ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user(),
                'role' => $request->user()->role,
            ]
        ]);
    }

    private function getRedirectRoute($role)
    {
        return match($role) {
            'admin' => '/admin/dashboard',
            'faculty' => '/faculty/dashboard',
            'trainee' => '/trainee/dashboard',
            default => '/dashboard',
        };
    }
}