<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\CourseReview;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Section;
use App\Models\CourseAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    protected CourseService $courseService;

    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * Создать новый курс.
     *
     * @param StoreCourseRequest $request
     * @return JsonResponse
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['creator_id'] !== Auth::id()) {
            return response()->json(['error' => 'Нет прав для создания курса'], 403);
        }

        $course = $this->courseService->createCourse($validated);

        return response()->json(['message' => 'Курс успешно создан!', 'course' => $course], 201);
    }

    /**
     * Получить список курсов, созданных пользователем.
     *
     * @param int $creatorId
     * @return JsonResponse
     */
    public function getCourseByCreatorId(int $creatorId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        if ($creatorId != $user->id) {
            return response()->json(['error' => 'Нет прав для просмотра курсов другого пользователя'], 403);
        }

        $courses = Course::where('creator_id', $creatorId)
            ->withCount(['access as access_count' => function ($query) use ($creatorId) {
                $query->where('user_id', '!=', $creatorId);
            }])
            ->get()
            ->map(function ($course) {
                return [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'user_count' => $course->access_count,
                    'cover_image' => $course->cover_image_url,
                    'status' => $course->status,
                ];
            });

        return response()->json($courses, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Получить список курсов, приобретенных пользователем.
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getPurchasedCoursesByUserId(int $userId): JsonResponse
    {
        try {
            $currentUser = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        if ($userId != $currentUser->id) {
            return response()->json(['error' => 'Нет прав для просмотра курсов другого пользователя'], 403);
        }

        $courseAccesses = CourseAccess::where('user_id', $userId)->get();

        if ($courseAccesses->isEmpty()) {
            return response()->json([]);
        }

        $courseIds = $courseAccesses->pluck('course_id');

        $courses = Course::whereIn('id', $courseIds)
            ->with(['sections', 'sections.chapters', 'testSections'])
            ->get()
            ->filter(function ($course) {
                return !in_array($course->status, [0, 2]);
            })
            ->map(function ($course) use ($courseAccesses, $userId) {
                $courseAccess = $courseAccesses->firstWhere('course_id', $course->id);

                return [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'creator_id' => $course->creator_id,
                    'cover_image' => $course->cover_image_url,
                    'access_end_date' => $courseAccess ? $courseAccess->access_end_date : null,
                    'is_your_course' => $course->creator_id == $userId ? 'ваш курс' : null,
                ];
            });

        return response()->json($courses, 200, [], JSON_UNESCAPED_UNICODE);
    }


    public function addAccess(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|integer',
            'user_id' => 'required|integer',
            'duration' => 'required|integer'
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
        if ($validated['duration'] > 0) {
            $accessEndDate = Carbon::now()->addMonths($validated['duration']);
        } elseif ($validated['duration'] === -1) {
            $accessEndDate = null;
        }

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

    public function showCourseDetails(Request $request, int $courseId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userId = $request->query('user_id', $user->id);

        $hasAccess = CourseAccess::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['error' => 'У вас нет доступа к этому курсу'], 403);
        }

        $course = Course::with('sections')->find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Курс не найден'], 404);
        }

        $isCreator = Course::where('id', $courseId)
            ->where('creator_id', $userId)
            ->exists();

        $review = CourseReview::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->first(['rating', 'review', 'update_count']);

        $courseDetails = [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'cover_image' => $course->cover_image_url,
            'sections' => $course->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'photo_url' => $section->photo_url,
                ];
            }),
            'user_review' => [
                'rating' => $review->rating ?? null,
                'review' => $review->review ?? null,
                'update_count' => $review->update_count ?? 0,
            ],
        ];

        if ($isCreator) {
            $courseDetails['creator'] = true;
        }

        return response()->json($courseDetails, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function showSectionChapters(Request $request, $courseId, $sectionId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userId = $request->query('user_id', $user->id);

        $hasAccess = CourseAccess::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['error' => 'У вас нет доступа к этому курсу'], 403);
        }

        $section = Section::where('id', $sectionId)
            ->where('course_id', $courseId)
            ->with('chapters')
            ->first();

        if (!$section) {
            return response()->json(['error' => 'Раздел не найден'], 404);
        }

        $sectionDetails = [
            'id' => $section->id,
            'name' => $section->name,
            'chapters' => $section->chapters->map(function ($chapter) {
                return [
                    'id' => $chapter->id,
                    'name' => $chapter->name,
                ];
            }),
        ];


        return response()->json($sectionDetails, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function showChapterDetails(Request $request, $courseId, $sectionId, $chapterId): JsonResponse
    {
        \Log::info('Received request to show chapter details:', [
            'course_id' => $courseId,
            'section_id' => $sectionId,
            'chapter_id' => $chapterId,
            'user_id' => $request->query('user_id')
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userId = $request->query('user_id', $user->id);

        $hasAccess = CourseAccess::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['error' => 'У вас нет доступа к этому курсу'], 403);
        }

        $section = Section::where('id', $sectionId)
            ->where('course_id', $courseId)
            ->with(['chapters' => function ($query) use ($chapterId) {
                $query->where('id', $chapterId)
                    ->with(['titles' => function ($query) {
                        $query->with(['texts' => function ($textQuery) {
                            $textQuery->with(['photos', 'video' => function ($videoQuery) {
                                $videoQuery->select('id', 'video_url', 'title_text_id');
                            }]);
                        }, 'files', 'links']);
                    }]);
            }])
            ->first();

        if (!$section) {
            return response()->json(['error' => 'Раздел не найден'], 404);
        }

        $chapter = $section->chapters->first();

        if (!$chapter) {
            return response()->json(['error' => 'Глава не найдена'], 404);
        }

        $chapterDetails = [
            'id' => $chapter->id,
            'name' => $chapter->name,
            'titles' => $chapter->titles->map(function ($title) {
                return [
                    'id' => $title->id,
                    'subtitle' => $title->subtitle,
                    'texts' => $title->texts->map(function ($text) {
                        return [
                            'id' => $text->id,
                            'content' => $text->content,
                            'photos' => $text->photos->map(function ($photo) {
                                return [
                                    'id' => $photo->id,
                                    'photo_url' => $photo->photo_url,
                                ];
                            }),
                            'video' => $text->video->map(function ($video) {
                                return [
                                    'id' => $video->id,
                                    'video_url' => $video->video_url,
                                ];
                            }),
                        ];
                    }),
                    'files' => $title->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_name' => $file->file_name,
                            'file_url' => $file->file_url,
                        ];
                    }),
                    'links' => $title->links->map(function ($link) {
                        return [
                            'id' => $link->id,
                            'link_url' => $link->link_url,
                        ];
                    }),
                ];
            }),
        ];

        \Log::info('Successfully retrieved chapter details:', ['chapter_details' => $chapterDetails]);

        return response()->json($chapterDetails, 200, [], JSON_UNESCAPED_UNICODE);
    }


    public function showFullCourseDetails(Request $request, $courseId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userId = $request->query('user_id', $user->id);

        $requestedUser = User::find($userId);

        if (!$requestedUser) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        if ($requestedUser->role_id == 1) {
            $hasAccess = true;
        } else {
            $hasAccess = CourseAccess::where('course_id', $courseId)
                ->where('user_id', $userId)
                ->exists();
        }

        if (!$hasAccess) {
            return response()->json(['error' => 'У вас нет доступа к этому курсу'], 403);
        }

        $course = Course::with(['sections.chapters.titles.texts.photos', 'sections.chapters.titles.texts.video', 'sections.chapters.titles.files', 'sections.chapters.titles.links'])
            ->find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Курс не найден'], 404);
        }

        $courseDetails = [
            'id' => $course->id,
            'name' => $course->name,
            'price' => intval($course->price),
            'payment_telegram_link' => $course->payment_telegram_link,
            'description' => $course->description,
            'cover_image' => $course->cover_image_url,
            'sections' => $course->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'coverImage' => $section->photo_url,
                    'chapters' => $section->chapters->map(function ($chapter) {
                        return [
                            'id' => $chapter->id,
                            'title' => $chapter->name,
                            'subChapters' => $chapter->titles->map(function ($title) {
                                return [
                                    'title' => $title->subtitle,
                                    'texts' => $title->texts->map(function ($text) {
                                        return [
                                            'id' => $text->id,
                                            'content' => $text->content,
                                            'images' => $text->photos->map(function ($photo) {
                                                return $photo->photo_url;
                                            })->toArray(),
                                            'video' => $text->video->isEmpty() ? null : $text->video->map(function ($photo) {
                                                return $photo->video_url;
                                            })->toArray(),
                                        ];
                                    }),
                                ];
                            }),
                        ];
                    }),
                ];
            }),
        ];

        return response()->json($courseDetails, 200);
    }

    /**
     * Обновить курс.
     *
     * @param UpdateCourseRequest $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function update(UpdateCourseRequest $request, int $courseId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        if ($request->creator_id !== $user->id) {
            return response()->json(['error' => 'Нет прав для обновления курса'], 403);
        }

        $updated = $this->courseService->updateCourse($request->validated(), $courseId);

        if (!$updated) {
            return response()->json(['error' => 'Ошибка обновления курса'], 500);
        }

        return response()->json(['message' => 'Курс успешно обновлён!'], 200);
    }


    public function getCoursesForReview()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $courses = Course::where('status', 2)
            ->get(['id', 'name', 'cover_image_url', 'creator_id', 'status']);

        $creatorIds = $courses->pluck('creator_id')->unique();

        $users = User::whereIn('id', $creatorIds)->get()->keyBy('id');

        $formattedCourses = $courses->map(function ($course) use ($users) {
            $creator = $users->get($course->creator_id);
            return [
                'id' => $course->id,
                'name' => $course->name,
                'cover_image_url' => url($course->cover_image_url),
                'creator_id' => $course->creator_id,
                'creator_name' => $creator ? $creator->username : 'Неизвестно',
                'status' => 'На проверке'
            ];
        });

        return response()->json($formattedCourses);
    }

    public function updateStatus($id, Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $course = Course::findOrFail($id);

        $course->status = $validated['status'];
        $course->save();

        return response()->json([
            'message' => 'Статус курса успешно обновлён',
            'course' => $course,
        ]);
    }

    public function getAllCoursesForAdmin()
    {
        $courses = Course::all();
        return response()->json($courses);
    }

    public function destroyCourse($id)
    {
        $course = Course::find($id);

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully'], 200);
    }
}
