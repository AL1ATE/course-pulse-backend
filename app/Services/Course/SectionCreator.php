<?php

namespace App\Services\Course;

use App\Models\Course;
use App\Models\Section;

class SectionCreator
{
    protected ChapterCreator $chapterCreator;

    public function __construct(ChapterCreator $chapterCreator)
    {
        $this->chapterCreator = $chapterCreator;
    }

    public function create(Course $course, array $data): Section
    {
        $section = $course->sections()->create([
            'name' => $data['name'],
            'photo_url' => $data['photo_url'] ?? null,
        ]);

        foreach ($data['chapters'] as $chapterData){
            $this->chapterCreator->create($section, $chapterData);
        }

        return $section;
    }

}
