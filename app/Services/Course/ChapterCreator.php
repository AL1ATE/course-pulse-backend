<?php

namespace App\Services\Course;

use App\Models\Chapter;
use App\Models\Section;

class ChapterCreator
{
    protected TitleCreator $titleCreator;

    public function __construct(TitleCreator $titleCreator)
    {
        $this->titleCreator = $titleCreator;
    }

    public function create(Section $section, array $data): Chapter
    {
        $chapter = $section->chapters()->create([
            'name' => $data['title'],
        ]);

        foreach ($data['subChapters'] as $subChapterData){
            $this->titleCreator->create($chapter, $subChapterData);
        }

        return $chapter;
    }
}
