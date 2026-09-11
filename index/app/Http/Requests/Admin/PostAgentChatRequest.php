<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'context.doc' => ['nullable', 'string'],
            'context.source_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
