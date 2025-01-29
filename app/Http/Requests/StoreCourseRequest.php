<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
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
            'payment_telegram_link' => 'nullable|string|max:255',
        ];
    }
}
