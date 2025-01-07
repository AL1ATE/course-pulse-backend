<?php

namespace App\Http\Controllers;

use App\Models\CourseReview;
use App\Models\Text;
use App\Models\Title;
use App\Models\TitleFile;
use App\Models\TitleLink;
use App\Models\TitlePhoto;
use App\Models\TitleVideo;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Section;
use App\Models\Chapter;
use App\Models\TestSection;
use App\Models\CourseAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\JsonResponse;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('Received request data:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'cover_image_url' => 'nullable|url',
            'sections' => 'required|array|min:1',
            'sections.*.name' => 'required|string|max:255',
            'sections.*.photo_url' => 'nullable|url',
            'sections.*.chapters' => 'required|array|min:1',
            'sections.*.chapters.*.title' => 'required|string|max:255',
            'sections.*.chapters.*.subChapters' => 'required|array|min:1',
            'sections.*.chapters.*.subChapters.*.title' => 'required|string|max:255',
            'sections.*.chapters.*.subChapters.*.texts' => 'required|array|min:1',
            'sections.*.chapters.*.subChapters.*.texts.*.content' => 'required|string',
            'sections.*.chapters.*.subChapters.*.texts.*.images' => 'nullable|array',
            'sections.*.chapters.*.subChapters.*.texts.*.images.*' => 'nullable|url',
            'sections.*.chapters.*.subChapters.*.texts.*.video' => 'nullable|url',
            'sections.*.chapters.*.subChapters.*.texts.*.files' => 'nullable|array',
            'sections.*.chapters.*.subChapters.*.texts.*.files.*.name' => 'nullable|string|max:255',
            'sections.*.chapters.*.subChapters.*.texts.*.files.*.url' => 'nullable|url',
            'sections.*.chapters.*.subChapters.*.texts.*.links' => 'nullable|array',
            'sections.*.chapters.*.subChapters.*.texts.*.links.*' => 'nullable|url',
            'creator_id' => 'required|integer',
            'price' => 'nullable|numeric',
            'payment_telegram_link' => 'nullable|string|max:255'
        ]);

        \Log::info('Validated request data:', $validated);

        $userId = $validated['creator_id'];
        $currentUserId = Auth::id();

        if ($userId !== $currentUserId) {
            return response()->json(['error' => 'Нет прав для создания курса'], 403);
        }

        DB::beginTransaction();

        try {
            $price = $validated['price'];
            if (is_numeric($price)) {
                $price = number_format($price, 2, '.', '');
            } else {
                $price = '0.00';
            }

            $course = Course::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'cover_image_url' => $validated['cover_image_url'] ?? null,
                'creator_id' => $userId,
                'status' => 2,
                'price' => $price,
                'payment_telegram_link' => $validated['payment_telegram_link'] ?? null,
            ]);

            foreach ($validated['sections'] as $sectionData) {
                $section = Section::create([
                    'course_id' => $course->id,
                    'name' => $sectionData['name'],
                    'photo_url' => $sectionData['photo_url'] ?? null,
                ]);

                foreach ($sectionData['chapters'] as $chapterData) {
                    $chapter = Chapter::create([
                        'section_id' => $section->id,
                        'name' => $chapterData['title'],
                    ]);

                    foreach ($chapterData['subChapters'] as $subChapterData) {
                        $subChapter = Title::create([
                            'chapter_id' => $chapter->id,
                            'subtitle' => $subChapterData['title'],
                        ]);

                        if (isset($subChapterData['texts'])) {
                            foreach ($subChapterData['texts'] as $textData) {
                                $text = Text::create([
                                    'title_id' => $subChapter->id,
                                    'content' => $textData['content'],
                                ]);

                                // Сохраняем изображения
                                if (isset($textData['images'])) {
                                    foreach ($textData['images'] as $imageUrl) {
                                        if ($imageUrl) {
                                            TitlePhoto::create([
                                                'title_text_id' => $text->id,
                                                'photo_url' => $imageUrl,
                                            ]);
                                        }
                                    }
                                }

                                if (isset($textData['video']) && $textData['video']) {
                                    TitleVideo::create([
                                        'title_text_id' => $text->id,
                                        'video_url' => $textData['video'],
                                    ]);
                                }

                                if (isset($textData['files'])) {
                                    foreach ($textData['files'] as $file) {
                                        if (isset($file['name']) && isset($file['url'])) {
                                            TitleFile::create([
                                                'text_id' => $text->id,
                                                'file_name' => $file['name'],
                                                'file_url' => $file['url'],
                                            ]);
                                        }
                                    }
                                }

                                if (isset($textData['links'])) {
                                    foreach ($textData['links'] as $linkUrl) {
                                        if ($linkUrl) {
                                            TitleLink::create([
                                                'text_id' => $text->id,
                                                'link_url' => $linkUrl,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            CourseAccess::create([
                'course_id' => $course->id,
                'user_id' => $userId,
            ]);

            DB::commit();
            return response()->json(['message' => 'Курс успешно создан!'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating course:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Произошла ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function getCourseByCreatorId($creatorId): JsonResponse
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

    public function getPurchasedCoursesByUserId($userId): JsonResponse
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


    public function addAccess(Request $request)
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

    public function showCourseDetails(Request $request, $courseId): JsonResponse
    {
        \Log::info('Received request to show course details:', [
            'course_id' => $courseId,
            'user_id' => $request->query('user_id')
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        // Получаем user_id из запроса
        $userId = $request->query('user_id', $user->id);

        // Проверяем, есть ли доступ к курсу
        $hasAccess = CourseAccess::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['error' => 'У вас нет доступа к этому курсу'], 403);
        }

        // Находим курс с его разделами
        $course = Course::with('sections')->find($courseId);

        if (!$course) {
            return response()->json(['error' => 'Курс не найден'], 404);
        }

        // Проверяем, является ли пользователь создателем курса
        $isCreator = Course::where('id', $courseId)
            ->where('creator_id', $userId) // Предполагается, что в таблице 'courses' есть поле 'creator_id'
            ->exists();

        // Получаем отзыв пользователя о курсе
        $review = CourseReview::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->first(['rating', 'review', 'update_count']);

        // Формируем ответ
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

        // Если пользователь является создателем курса, добавляем поле 'creator'
        if ($isCreator) {
            $courseDetails['creator'] = true;
        }

        \Log::info('Successfully retrieved course details:', ['course_details' => $courseDetails]);

        return response()->json($courseDetails, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function showSectionChapters(Request $request, $courseId, $sectionId): JsonResponse
    {
        \Log::info('Received request to show section chapters:', [
            'course_id' => $courseId,
            'section_id' => $sectionId,
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

        \Log::info('Successfully retrieved section chapters:', ['section_details' => $sectionDetails]);

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
                        // Включаем тексты, фотографии и видео, связанные с ними
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

    public function update(Request $request, $courseId): JsonResponse
    {
        \Log::info('Received request data:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'sections' => 'required|array|min:1',
            'sections.*.name' => 'required|string|max:255',
            'sections.*.coverImage' => 'nullable|url',
            'sections.*.chapters' => 'required|array|min:1',
            'sections.*.chapters.*.title' => 'required|string|max:255',
            'sections.*.chapters.*.subChapters' => 'required|array|min:1',
            'sections.*.chapters.*.subChapters.*.title' => 'required|string|max:255',
            'sections.*.chapters.*.subChapters.*.texts' => 'required|array|min:1',
            'sections.*.chapters.*.subChapters.*.texts.*.content' => 'required|string',
            'sections.*.chapters.*.subChapters.*.texts.*.images' => 'nullable|array',
            'sections.*.chapters.*.subChapters.*.texts.*.images.*' => 'nullable|url',
            'sections.*.chapters.*.subChapters.*.texts.*.video' => 'nullable|url',
            'creator_id' => 'required|integer',
            'cover_image_url' => 'nullable|url',
            'price' => 'nullable|numeric',
            'payment_telegram_link' => 'nullable|string|max:255'
        ]);

        \Log::info('Validated request data:', $validated);

        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Пользователь не аутентифицирован'], 401);
        }

        $userId = $validated['creator_id'];
        $currentUserId = $user->id;

        if ($userId !== $currentUserId) {
            return response()->json(['error' => 'Нет прав для обновления курса'], 403);
        }

        DB::beginTransaction();

        try {
            $course = Course::findOrFail($courseId);

            // Обработка значений price и payment_telegram_link
            $price = isset($validated['price']) && is_numeric($validated['price']) ? number_format($validated['price'], 2, '.', '') : '0.00';
            $paymentTelegramLink = $validated['payment_telegram_link'] ?? $course->payment_telegram_link;

            $course->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'cover_image_url' => $validated['cover_image_url'] ?? $course->cover_image_url,
                'price' => $price,
                'payment_telegram_link' => $paymentTelegramLink,
            ]);

            Section::where('course_id', $courseId)->delete();

            foreach ($validated['sections'] as $sectionData) {
                $section = Section::create([
                    'course_id' => $course->id,
                    'name' => $sectionData['name'],
                    'photo_url' => $sectionData['coverImage'] ?? null,
                ]);

                foreach ($sectionData['chapters'] as $chapterData) {
                    $chapter = Chapter::create([
                        'section_id' => $section->id,
                        'name' => $chapterData['title'],
                    ]);

                    foreach ($chapterData['subChapters'] as $subChapterData) {
                        $subChapter = Title::create([
                            'chapter_id' => $chapter->id,
                            'subtitle' => $subChapterData['title'],
                        ]);

                        foreach ($subChapterData['texts'] as $textData) {
                            $text = Text::create([
                                'title_id' => $subChapter->id,
                                'content' => $textData['content'],
                            ]);

                            if ($text->id && isset($textData['images'])) {
                                foreach ($textData['images'] as $imageUrl) {
                                    if ($imageUrl) {
                                        TitlePhoto::create([
                                            'title_text_id' => $text->id,
                                            'photo_url' => $imageUrl,
                                        ]);
                                    }
                                }
                            }
                            if (isset($textData['video']) && $textData['video']) {
                                TitleVideo::create([
                                    'title_text_id' => $text->id,
                                    'video_url' => $textData['video'],
                                ]);
                            }
                        }
                    }
                }
            }

            if (isset($validated['testSections'])) {
                TestSection::where('course_id', $course->id)->delete();
                foreach ($validated['testSections'] as $testSectionData) {
                    TestSection::create([
                        'course_id' => $course->id,
                        'name' => $testSectionData['name'],
                    ]);
                }
            }

            $course->status = 2;
            $course->save();

            DB::commit();

            return response()->json(['message' => 'Курс успешно обновлён!'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating course:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Произошла ошибка: ' . $e->getMessage()], 500);
        }
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
