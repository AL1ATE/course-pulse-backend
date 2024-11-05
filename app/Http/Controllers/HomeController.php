<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseAccess;
use App\Models\CourseReview;
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

        return response()->json($courses, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
