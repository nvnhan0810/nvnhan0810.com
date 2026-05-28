<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'translations.en' => 'nullable|array',
            'translations.en.title' => 'nullable|string|required_with:translations.en.content',
            'translations.en.description' => 'nullable|string',
            'translations.en.content' => 'nullable|string|required_with:translations.en.title',
            'translations.en.source_url' => 'nullable|url|max:2048',
            'translations.vi' => 'nullable|array',
            'translations.vi.title' => 'nullable|string|required_with:translations.vi.content',
            'translations.vi.description' => 'nullable|string',
            'translations.vi.content' => 'nullable|string|required_with:translations.vi.title',
            'translations.vi.source_url' => 'nullable|url|max:2048',
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
            $sourceUrl = trim($translations[$locale]['source_url'] ?? '');

            if ($title === '' && $content === '') {
                unset($translations[$locale]);
                continue;
            }

            $translations[$locale]['source_url'] = $sourceUrl !== '' ? $sourceUrl : null;
        }

        $this->merge([
            'translations' => $translations,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $translations = $this->input('translations', []);
            $hasContent = false;

            foreach (Post::SUPPORTED_LOCALES as $locale) {
                $title = trim(data_get($translations, "{$locale}.title", ''));
                $content = trim(data_get($translations, "{$locale}.content", ''));

                if ($title !== '' && $content !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (! $hasContent) {
                $validator->errors()->add(
                    'translations',
                    'At least one locale (en or vi) must include both title and content.'
                );
            }
        });
    }
}
