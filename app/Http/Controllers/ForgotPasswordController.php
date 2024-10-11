<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Mail\VerificationCodeMail;
use Illuminate\Http\Request;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    public function sendResetCode(Request $request)
    {
        // Валидация входных данных
        $request->validate(['email' => 'required|email']);

        // Генерация нового кода
        $token = Str::random(6);

        // Проверка, есть ли запись для данного email
        $existingToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if ($existingToken) {
            // Обновление кода, если запись уже существует
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->update([
                    'token' => $token,
                    'created_at' => now(),
                ]);
        } else {
            // Создание новой записи, если email отсутствует в базе
            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now(),
            ]);
        }

        // Логируем информацию о коде и email
        Log::info('Сохранен код сброса пароля в базе данных', [
            'email' => $request->email,
            'token' => $token
        ]);

        // Отправка кода на email
        try {
            Mail::to($request->email)->send(new VerificationCodeMail($token));
            Log::info('Письмо с кодом сброса пароля отправлено на', ['email' => $request->email]);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки почты', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);
            return response()->json(['message' => 'Ошибка отправки кода'], 500);
        }

        return response()->json(['message' => 'Reset code sent to ' . $request->email]);
    }

    public function verifyToken(Request $request)
    {
        // Валидация входных данных
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
        ]);

        // Проверка кода
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetToken) {
            return response()->json(['message' => 'Invalid reset code'], 400);
        }

        return response()->json(['message' => 'Reset code is valid']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetToken) {
            return response()->json(['message' => 'Invalid reset code'], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = bcrypt($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully']);
    }
}
