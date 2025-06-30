<?php

namespace App\Services\Course;

use App\Models\Text;
use App\Models\Title;
use App\Models\TitlePhoto;
use App\Models\TitleVideo;

class TextCreator
{
    public function create(Title $title, array $data): Text
    {
        $text = $title->texts()->create([
            'content' => $data['content'],
        ]);

        foreach ($data['images'] ?? [] as $imageUrl){
            if ($imageUrl) {
                TitlePhoto::create([
                    'title_text_id' => $text->id,
                    'photo_url' => $imageUrl,
                ]);
            }
        }

        if (!empty($data['video'])){
            TitleVideo::create([
                'title_text_id' => $text->id,
                'video_url' => $data['video'],
            ]);
        }

        return $text;
    }
}
