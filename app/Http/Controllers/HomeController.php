<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\JsonResponse;

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

    public function getCourseDetails($id): JsonResponse
    {
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
            'price' => intval($course->price),
            'participants_count' => $course->access()->where('user_id', '!=', $course->creator_id)->distinct()->count(),
        ];

        return response()->json($courseData);
    }
}
