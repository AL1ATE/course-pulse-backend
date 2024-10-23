<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Transaction;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request, $courseId)
    {
        // Найти курс по его ID
        $course = Course::findOrFail($courseId);

        // Текущий пользователь
        $user = Auth::user();

        // Создаем транзакцию с курсом и пользователем
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'pending',
        ]);

        // Формируем параметры для отправки в FreeKassa
        $paymentUrl = $this->generateFreeKassaUrl($transaction);

        // Перенаправляем пользователя на оплату
        return redirect($paymentUrl);
    }

    private function generateFreeKassaUrl(Transaction $transaction)
    {
        // Параметры FreeKassa
        $merchantId = config('services.freekassa.merchant_id');
        $secretKey = config('services.freekassa.secret_key');
        $amount = $transaction->amount;
        $orderId = $transaction->id;

        // Хэшируем данные
        $sign = md5($merchantId . ':' . $amount . ':' . $secretKey . ':' . $orderId);

        // URL для редиректа
        $paymentUrl = "https://www.free-kassa.ru/merchant/cash.php?" . http_build_query([
                'm' => $merchantId,
                'oa' => $amount,
                'o' => $orderId,
                's' => $sign,
                'us_user_id' => $transaction->user_id
            ]);

        return $paymentUrl;
    }

    // Обработка колбэка после оплаты
    public function paymentCallback(Request $request)
    {
        // Получаем данные от FreeKassa
        $transactionId = $request->input('MERCHANT_ORDER_ID');
        $amount = $request->input('AMOUNT');
        $sign = $request->input('SIGN');

        // Найти транзакцию
        $transaction = Transaction::findOrFail($transactionId);

        // Проверяем валидность хэша
        $generatedSign = md5(config('services.freekassa.merchant_id') . ':' . $amount . ':' . config('services.freekassa.secret_key') . ':' . $transactionId);

        if ($sign === $generatedSign && $transaction->amount == $amount) {
            // Обновляем статус транзакции
            $transaction->status = 'paid';
            $transaction->save();

            // Обновляем баланс пользователя
            $userBalance = UserBalance::firstOrCreate(
                ['user_id' => $transaction->user_id],
                ['balance' => 0.00]
            );
            $userBalance->balance += $transaction->amount;
            $userBalance->save();

            return response()->json(['status' => 'success'], 200);
        }

        return response()->json(['status' => 'error'], 400);
    }
}
