<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Section;
use App\Models\Text;
use App\Models\Title;
use App\Models\TitlePhoto;
use App\Models\TitleVideo;
use Illuminate\Support\Facades\DB;
use App\Services\Course\CourseCreator;

class CourseService
{
    protected CourseCreator $courseCreator;

    public function __construct(CourseCreator $courseCreator){
        $this->courseCreator = $courseCreator;
    }

    public function createCourse(array $data)
    {
        return DB::transaction(fn() => $this->courseCreator->create($data));
    }

    public function updateCourse(array $validated, int $courseId): bool
    {
        DB::beginTransaction();

        try {
            $course = Course::findOrFail($courseId);

            $price = isset($validated['price']) && is_numeric($validated['price']) ? number_format($validated['price'], 2, '.', '') : '0.00';
            $paymentTelegramLink = $validated['payment_telegram_link'] ?? $course->payment_telegram_link;

            $course->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'cover_image_url' => $validated['cover_image_url'] ?? $course->cover_image_url,
                'price' => $price,
                'payment_telegram_link' => $paymentTelegramLink,
            ]);

            // Удаление старых секций перед обновлением
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

                            if (!empty($textData['video'])) {
                                TitleVideo::create([
                                    'title_text_id' => $text->id,
                                    'video_url' => $textData['video'],
                                ]);
                            }
                        }
                    }
                }
            }

            $course->status = 2;
            $course->save();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating course:', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

