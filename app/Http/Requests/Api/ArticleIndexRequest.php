<?php

namespace App\Http\Requests\Api;

use App\Services\ArticleContentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleIndexRequest extends FormRequest
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
            'category' => ['nullable', 'string', 'max:100'],
            'sub' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'content_type' => ['nullable', Rule::enum(ArticleContentType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'breaking' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
            'editors_choice' => ['nullable', 'boolean'],
            'importance_min' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sort' => ['nullable', 'in:latest,popular,trending,importance,oldest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'min:2', 'max:200'],
            'exclude_ids' => ['nullable', 'array'],
            'exclude_ids.*' => ['integer'],
        ];
    }
}
