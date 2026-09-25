<?php

namespace Modules\Blog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Blog\Domain\Enums\PostStatus;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'source_url' => 'nullable|url|max:2048',
            'status' => ['required', Rule::enum(PostStatus::class)],
            'published_at' => 'nullable|date',
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
            'status' => $this->input('status', PostStatus::Draft->value),
        ]);
    }
}
