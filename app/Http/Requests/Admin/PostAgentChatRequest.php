<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostAgentChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            'session_id' => ['nullable', 'uuid'],
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'context' => ['required', 'array'],
            'context.docs' => ['required', 'array'],
            'context.docs.en' => ['nullable', 'string'],
            'context.docs.vi' => ['nullable', 'string'],
            'context.source_urls' => ['nullable', 'array'],
            'context.source_urls.en' => ['nullable', 'string', 'max:2048'],
            'context.source_urls.vi' => ['nullable', 'string', 'max:2048'],
            'context.active_locale' => ['nullable', Rule::in(Post::SUPPORTED_LOCALES)],
        ];
    }
}
