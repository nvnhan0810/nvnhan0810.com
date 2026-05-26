<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'translations' => 'required|array',
            'translations.en' => 'required|array',
            'translations.en.title' => 'required|string',
            'translations.en.description' => 'nullable|string',
            'translations.en.content' => 'required|string',
            'translations.vi' => 'nullable|array',
            'translations.vi.title' => 'nullable|string',
            'translations.vi.description' => 'nullable|string',
            'translations.vi.content' => 'nullable|string',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'required|string',
            'series_ids' => 'nullable|array',
            'series_ids.*' => 'required|integer',
        ];
    }

    protected function prepareForValidation(): void
    {
        $translations = $this->input('translations', []);

        foreach (Post::SUPPORTED_LOCALES as $locale) {
            if (! isset($translations[$locale])) {
                continue;
            }

            $title = trim($translations[$locale]['title'] ?? '');
            $content = trim($translations[$locale]['content'] ?? '');

            if ($title === '' && $content === '') {
                unset($translations[$locale]);
            }
        }

        $this->merge([
            'translations' => $translations,
        ]);
    }
}
