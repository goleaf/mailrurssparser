<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ArticleSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'content_type' => ['nullable', 'in:news,article,opinion,analysis,interview'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:relevance,latest,popular'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
