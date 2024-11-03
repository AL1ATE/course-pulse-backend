<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseAccess;
use App\Models\CourseReview;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseInfo extends Controller
{
    public function getCourseDetails($id, Request $request): JsonResponse
    {
        $userId = $request->input('user_id');

        $course = Course::with(['creator.profile'])
            ->where('id', $id)
            ->where('status', 1)
            ->firstOrFail();

        $courseData = [
            'id' => $course->id,
            'name' => $course->name,
            'creator_id' => $course->creator_id,
            'username' => $course->creator->username,
            'avatar_url' => $course->creator->profile ? $course->creator->profile->avatar_url : null,
            'cover_image_url' => $course->cover_image_url,
            'description' => $course->description,
            'payment_telegram_link' => $course->payment_telegram_link,
            'price' => intval($course->price),
            'participants_count' => $course->access()->where('user_id', '!=', $course->creator_id)->distinct()->count(),
        ];

        if ($userId) {
            $hasAccess = CourseAccess::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->exists();

            $courseData['has_access'] = $hasAccess;
        }

        $ratingData = CourseReview::where('course_id', $course->id)
            ->selectRaw('ROUND(AVG(rating), 1) as average_rating, COUNT(*) as ratings_count')
            ->first();

        if ($ratingData) {
            $courseData['average_rating'] = $ratingData->average_rating;
            $courseData['ratings_count'] = $ratingData->ratings_count;
        } else {
            $courseData['average_rating'] = null;
            $courseData['ratings_count'] = 0;
        }

        return response()->json($courseData);
    }

    public function freeCourseAccess(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'user_id' => 'required|integer'
        ]);

        $course = Course::find($validated['course_id']);
        if (!$course) {
            return response()->json(['error' => 'Курс не существует'], 404);
        }

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json(['error' => 'Пользователь не существует'], 404);
        }

        $accessEndDate = null;

        CourseAccess::updateOrCreate(
            [
                'course_id' => $validated['course_id'],
                'user_id' => $validated['user_id']
            ],
            [
                'access_end_date' => $accessEndDate
            ]
        );

        return response()->json(['message' => 'true'], 201);
    }

    public function getReviewsByCourseId($courseId)
    {
        \Carbon\Carbon::setLocale('ru');

        $reviews = CourseReview::where('course_id', $courseId)
            ->whereNotNull('review') // Условие для отбора отзывов с текстом
            ->where('review', '!=', '') // Исключаем пустые строки
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($review) {
                $latestDate = $review->created_at > $review->updated_at ? $review->created_at : $review->updated_at;

                $timeAgo = \Carbon\Carbon::parse($latestDate)->diffForHumans(null, false, true);

                $user = User::find($review->user_id);
                $profile = Profile::where('user_id', $review->user_id)->first();

                return [
                    'id' => $review->id,
                    'user_id' => $review->user_id,
                    'name' => $user ? $user->username : null,
                    'avatar' => $profile ? $profile->avatar_url : null,
                    'rating' => $review->rating,
                    'review' => $review->review,
                    'date' => $timeAgo,
                ];
            });

        return response()->json($reviews);
    }
}
