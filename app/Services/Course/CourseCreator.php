<?php

namespace App\Services\Course;

use App\Models\Course;
use App\Models\CourseAccess;

class CourseCreator
{
    protected SectionCreator $sectionCreator;

    public function __construct(SectionCreator $sectionCreator)
    {
        $this->sectionCreator = $sectionCreator;
    }

    public function create(array $data): Course
    {
        $course = Course::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'cover_image_url' => $data['cover_image_url'],
            'creator_id' => $data['creator_id'],
            'status' => 2,
            'price' => $this->normalizePrice($data['name']),
            'payment_telegram_link' => $data['payment_telegram_link'] ?? null,
        ]);

        CourseAccess::create([
            'course_id' => $course->id,
            'user_id' => $course->creator_id,
        ]);

        foreach ($data['sections'] as $sectionData){
            $this->sectionCreator->create($course, $sectionData);
        }

        return $course;
    }

    private function normalizePrice($price): string
    {
        return is_numeric($price) ? number_format($price, 2, '.', '') : '0.00';
    }
}
