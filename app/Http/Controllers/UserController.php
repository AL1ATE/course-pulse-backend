<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\RoleUpgradeRequest;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(): \Illuminate\Http\JsonResponse
    {
        $users = User::with('role')->get();

        $formattedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->username,
                'email' => $user->email,
                'role' => $user->role ? $user->role->role_name : 'Не назначена',
                'status' => $user->status,
            ];
        });

        return response()->json($formattedUsers);
    }

    public function delete($id): \Illuminate\Http\JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден.'], 404);
        }

        RoleUpgradeRequest::where('user_id', $id)->delete();

        $profile = Profile::where('user_id', $id)->first();

        if ($profile) {
            $profile->delete();
        }

        $user->delete();

        return response()->json(['message' => 'Пользователь и связанные данные успешно удалены.']);
    }



    /**
     * Update the specified user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'role' => 'required|string|exists:roles,role_name',
            'status' => 'nullable|string|in:active,inactive,blocked',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден.'], 404);
        }

        $role = Role::where('role_name', $request->input('role'))->first();

        if (!$role) {
            return response()->json(['error' => 'Роль не найдена.'], 404);
        }

        $user->username = $request->input('name');
        $user->email = $request->input('email');
        $user->role_id = $role->id;

        if ($request->has('status')) {
            $user->status = $request->input('status');
        }

        $user->save();

        return response()->json(['message' => 'Данные пользователя успешно обновлены.']);
    }
}
