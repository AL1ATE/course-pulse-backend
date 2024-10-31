<?php

namespace App\Http\Controllers;

use App\Models\CourseReview;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseRatingsController extends Controller
{
    public function store(Request $request)
    {
        // Валидация входящих данных
        $validatedData = $request->validate([
            'user_id' => 'required|integer',
            'course_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:255',
        ]);

        // Попробуем найти существующий отзыв
        $courseReview = CourseReview::where('course_id', $validatedData['course_id'])
            ->where('user_id', $validatedData['user_id'])
            ->first();

        if ($courseReview) {
            // Обновляем существующую запись
            $courseReview->update_count += 1;
            $courseReview->rating = $validatedData['rating'];
            $courseReview->review = $validatedData['review'] ?? null;
            $courseReview->save();
        } else {
            // Создаем новую запись
            CourseReview::create([
                'course_id' => $validatedData['course_id'],
                'user_id' => $validatedData['user_id'],
                'rating' => $validatedData['rating'],
                'review' => $validatedData['review'] ?? null,
                'update_count' => 1,
            ]);
        }

        return response()->json(['message' => 'Review saved successfully.']);
    }
}
