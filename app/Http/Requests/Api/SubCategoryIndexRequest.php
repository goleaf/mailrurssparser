<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubCategoryIndexRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer:strict', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.max' => 'Slug рубрики не должен превышать 100 символов.',
            'category_id.integer' => 'Идентификатор рубрики должен быть целым числом.',
            'category_id.exists' => 'Выбранная рубрика не найдена.',
            'is_active.boolean' => 'Фильтр активности должен быть булевым значением.',
            'per_page.integer' => 'Размер страницы должен быть целым числом.',
            'per_page.min' => 'Размер страницы должен быть не меньше 1.',
            'per_page.max' => 'Размер страницы не должен превышать 100.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('category')) && is_string($this->route('slug'))) {
            $this->merge([
                'category' => $this->route('slug'),
            ]);
        }
    }
}
