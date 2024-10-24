<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    public function getCoursesForHome(): JsonResponse
    {
        $courses = Course::with(['creator.profile'])
            ->where('status', 1)
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'creator_id' => $course->creator_id,
                    'username' => $course->creator->username,
                    'avatar_url' => $course->creator->profile ? $course->creator->profile->avatar_url : null,
                    'cover_image_url' => $course->cover_image_url,
                    'price' => intval($course->price),
                ];
            });

        return response()->json($courses);
    }

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

            if ($hasAccess) {
                $courseData['has_access'] = true;
            } else {
                $courseData['has_access'] = false;
            }
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
}
