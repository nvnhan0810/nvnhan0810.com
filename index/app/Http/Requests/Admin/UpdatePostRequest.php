<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'source_url' => 'nullable|url|max:2048',
            'is_published' => 'required|boolean',
            'published_at' => 'required|date',
            'tags' => 'nullable|array',
            'tags.*' => 'required|string',
            'series_ids' => 'nullable|array',
            'series_ids.*' => 'required|integer',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sourceUrl = trim((string) $this->input('source_url', ''));

        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
            'content' => trim((string) $this->input('content', '')),
            'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
        ]);
    }
}
