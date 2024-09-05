<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $rememberMe = $request->input('remember_me', false);

        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|string|min:6|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if ($rememberMe) {
            JWTAuth::factory()->setTTL(1440 * 7);
        } else {
            JWTAuth::factory()->setTTL(60);
        }

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }


    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role_id' => 3,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'avatar_url' => '',
            'background_image_url' => '',
            'profile_description' => '',
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->respondWithToken($token);
    }

    protected function respondWithToken($token)
    {
        $user = JWTAuth::setToken($token)->toUser();
        $profile = Profile::where('user_id', $user->id)->first();

        $customClaims = [
            'id' => $user->id,
            'name' => $user->username,
            'avatar_url' => $profile ? $profile->avatar_url : '',
            'background_image_url' => $profile ? $profile->background_image_url : '',
            'profile_description' => $profile ? $profile->profile_description : '',
            'role' => $user->role->role_name,
        ];

        $token = JWTAuth::claims($customClaims)->fromUser($user);

        return response()->json([
            'token' => $token,
            'role' => $user->role->role_name,
        ]);
    }
}
