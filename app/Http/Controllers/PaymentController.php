<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Transaction;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request, $courseId)
    {
        $request->validate([
            'payment_method' => 'required|string', // валидируем способ оплаты
        ]);

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

        // Получаем выбранный способ оплаты
        $paymentMethod = $request->input('payment_method');

        // Формируем параметры для отправки в FreeKassa
        $paymentUrl = $this->generateFreeKassaUrl($transaction, $paymentMethod);

        // Возвращаем URL для редиректа
        return response()->json(['payment_url' => $paymentUrl]);
    }

    private function generateFreeKassaUrl(Transaction $transaction, $paymentMethod)
    {
        // Параметры FreeKassa
        $merchantId = config('services.freekassa.merchant_id');
        $secretKey = config('services.freekassa.secret_key');
        $amount = $transaction->amount;
        $orderId = $transaction->id;
        $currency = 'RUB'; // Устанавливаем валюту, можно изменить, если потребуется

        // Код способа оплаты для FreeKassa
        $paymentMethodCode = $this->getPaymentMethodCode($paymentMethod);

        // Хэшируем данные для подписи
        $sign = md5($merchantId . ':' . $amount . ':' . $secretKey . ':' . $currency . ':' . $orderId);

        // URL для редиректа на страницу оплаты
        $paymentUrl = "https://pay.freekassa.com/?" . http_build_query([
                'm' => $merchantId,
                'oa' => $amount,
                'o' => $orderId,
                's' => $sign,
                'currency' => $currency,
                'i' => $paymentMethodCode, // передаем код способа оплаты
                'lang' => 'ru',
            ]);

        return $paymentUrl;
    }

    private function getPaymentMethodCode($paymentMethod)
    {
        // Соответствие способов оплаты кодам FreeKassa
        $paymentMethods = [
            'sbp' => 42,     // СБП
            'iomoney' => 6,  // IOMoney
            'mir' => 12,     // Карта Мир
        ];

        // Возвращаем код, если он существует, иначе null
        return $paymentMethods[$paymentMethod] ?? null;
    }

    // Обработка колбэка после оплаты
    public function paymentCallback(Request $request)
    {
        // Проверка IP-адреса
        if (!$this->isValidIP()) {
            die("Hacking attempt!");
        }

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

    private function isValidIP()
    {
        $validIPs = [
            '168.119.157.136',
            '168.119.60.227',
            '178.154.197.79',
            '51.250.54.238'
        ];
        return in_array($this->getIP(), $validIPs);
    }

    private function getIP()
    {
        return request()->ip();
    }
}
