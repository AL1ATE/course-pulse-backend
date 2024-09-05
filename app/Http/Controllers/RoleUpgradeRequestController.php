<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RoleUpgradeRequest;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

class RoleUpgradeRequestController extends Controller
{
    // Метод для отправки запроса на повышение роли
    public function sendRequest(Request $request)
    {
        $user = Auth::user();
        $requestedRole = $request->input('requested_role');

        $existingRequest = RoleUpgradeRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json(['message' => 'У вас уже есть запрос на повышение роли, ожидающий рассмотрения.'], 400);
        }

        $role = Role::where('role_name', $requestedRole)->first();
        if (!$role) {
            return response()->json(['message' => 'Указанная роль не найдена.'], 404);
        }

        // Создание нового запроса
        $roleUpgradeRequest = new RoleUpgradeRequest();
        $roleUpgradeRequest->user_id = $user->id;
        $roleUpgradeRequest->requested_role = $role->role_name;
        $roleUpgradeRequest->status = 'pending';
        $roleUpgradeRequest->save();

        return response()->json(['message' => 'Запрос на повышение отправлен.'], 200);
    }

    public function getRequests()
    {
        $user = Auth::user();

        $adminRole = Role::where('role_name', 'admin')->first();
        if ($user->role_id !== $adminRole->id) {
            return response()->json(['message' => 'У вас нет прав для просмотра этого ресурса.'], 403);
        }

        $requests = RoleUpgradeRequest::where('status', 'pending')->get();

        return response()->json($requests);
    }


    // Метод для одобрения запроса (только для администраторов)
    public function approveRequest($id)
    {
        $admin = Auth::user();

        $adminRole = Role::where('role_name', 'admin')->first();
        if ($admin->role_id !== $adminRole->id) {
            return response()->json(['message' => 'У вас нет прав для выполнения этого действия.'], 403);
        }

        $request = RoleUpgradeRequest::findOrFail($id);

        $role = Role::where('role_name', $request->requested_role)->first();
        if (!$role) {
            return response()->json(['message' => 'Указанная роль не найдена.'], 404);
        }

        $user = User::findOrFail($request->user_id);
        $user->role_id = $role->id;
        $user->save();

        $request->status = 'approved';
        $request->admin_id = $admin->id;
        $request->save();

        return response()->json(['message' => 'Запрос на повышение одобрен.'], 200);
    }

    // Метод для отклонения запроса (только для администраторов)
    public function rejectRequest($id)
    {
        $admin = Auth::user();

        $adminRole = Role::where('role_name', 'admin')->first();
        if ($admin->role_id !== $adminRole->id) {
            return response()->json(['message' => 'У вас нет прав для выполнения этого действия.'], 403);
        }

        $request = RoleUpgradeRequest::findOrFail($id);

        // Обновление статуса запроса
        $request->status = 'rejected';
        $request->admin_id = $admin->id;
        $request->save();

        return response()->json(['message' => 'Запрос на повышение отклонен.'], 200);
    }
}
