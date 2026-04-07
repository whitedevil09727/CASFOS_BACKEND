<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
    }

    /**
     * Check if user is authenticated
     */
    public function check(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'authenticated' => false,
                'message' => 'Not authenticated'
            ], 401);
        }

        // Check if token is still valid
        if ($user->currentAccessToken()) {
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role' => $user->role,
                    ],
                    'role' => $user->role,
                    'redirect' => $this->getRedirectRoute($user->role),
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'authenticated' => false,
            'message' => 'Invalid or expired token'
        ], 401);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Log the logout activity (optional)
                \Log::info('User logged out', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                // Revoke the current access token
                $request->user()->currentAccessToken()->delete();
                
                // If using multiple tokens, you can revoke all tokens:
                // $request->user()->tokens()->delete();
                
                // If using sessions, clear them
                // Auth::logout();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Logout error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    

    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
                'role' => $user->role,
            ]
        ]);
    }

    private function getRedirectRoute($role)
    {
        return match($role) {
            'admin' => '/dashboard/admin',
            'faculty' => '/dashboard/faculty',
            'trainee' => '/dashboard/trainee',
            'course_clerk' => '/dashboard/course-clerk',
            default => '/dashboard',
        };
    }
}