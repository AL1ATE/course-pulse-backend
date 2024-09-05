<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Profile;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ProfileController extends Controller
{
    /**
     * Обновить пользователя и профиль по его id.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json(['message' => 'Пользователь не аутентифицирован'], 401);
        }

        if ($user->id !== (int) $id) {
            return response()->json(['message' => 'Нет прав для обновления данного пользователя'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'avatar_url' => 'nullable|url',
            'background_image_url' => 'nullable|url',
            'profile_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Неверные данные', 'errors' => $validator->errors()], 422);
        }

        $userToUpdate = User::find($id);

        if (!$userToUpdate) {
            return response()->json(['message' => 'Пользователь не найден'], 404);
        }

        $userToUpdate->username = $request->input('username');
        $userToUpdate->save();

        $profile = Profile::where('user_id', $id)->first();
        if (!$profile) {
            $profile = new Profile();
            $profile->user_id = $id;
        }

        if ($request->input('avatar_url')) {
            $profile->avatar_url = $request->input('avatar_url');
        }

        if ($request->input('background_image_url')) {
            $profile->background_image_url = $request->input('background_image_url');
        }

        $profile->profile_description = $request->input('profile_description', $profile->profile_description);
        $profile->save();

        try {
            $customClaims = [
                'avatar_url' => $profile->avatar_url,
                'background_image_url' => $profile->background_image_url,
                'profile_description' => $profile->profile_description,
            ];

            $newToken = JWTAuth::claims($customClaims)->fromUser($userToUpdate);

            return response()->json(['token' => $newToken], 200);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Ошибка при обновлении токена', 'error' => $e->getMessage()], 500);
        }
    }
}
