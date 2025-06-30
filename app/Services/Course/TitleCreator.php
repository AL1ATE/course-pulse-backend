<?php

namespace App\Services\Course;

use App\Models\Chapter;
use App\Models\Title;

class TitleCreator
{
    protected TextCreator $textCreator;

    public function __construct(TextCreator $textCreator)
    {
        $this->textCreator = $textCreator;
    }

    public function create(Chapter $chapter, array $data):Title
    {
        $title = $chapter->titles()->create([
            'subtitle' => $data['title'],
        ]);

        foreach ($data['texts'] as $textData){
            $this->textCreator->create($title, $textData);
        }

        return $title;
    }
}
