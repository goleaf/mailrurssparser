<?php

namespace App\Http\Requests\Api;

use App\Models\SubCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubCategoryRequest extends FormRequest
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
            'category_id' => ['required', 'integer:strict', 'exists:categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_categories', 'name')
                    ->where(fn (Builder $query): Builder => $query->where('category_id', (int) $this->integer('category_id')))
                    ->ignore($this->resolveSubCategoryId()),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('sub_categories', 'slug')->ignore($this->resolveSubCategoryId()),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'hex_color'],
            'icon' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Рубрика обязательна.',
            'category_id.integer' => 'Идентификатор рубрики должен быть целым числом.',
            'category_id.exists' => 'Выбранная рубрика не найдена.',
            'name.required' => 'Название подрубрики обязательно.',
            'name.max' => 'Название подрубрики не должно превышать 255 символов.',
            'name.unique' => 'Подрубрика с таким названием уже существует в выбранной рубрике.',
            'slug.alpha_dash' => 'Slug может содержать только латиницу, цифры, дефисы и подчеркивания.',
            'slug.unique' => 'Подрубрика с таким slug уже существует.',
            'color.hex_color' => 'Цвет должен быть корректным HEX-значением.',
            'icon.max' => 'Иконка не должна превышать 10 символов.',
            'is_active.boolean' => 'Флаг активности должен быть булевым значением.',
            'order.integer' => 'Порядок должен быть целым числом.',
            'order.min' => 'Порядок не может быть отрицательным.',
        ];
    }

    private function resolveSubCategoryId(): int|string|null
    {
        return $this->resolveSubCategory()?->getKey();
    }

    private function resolveSubCategory(): ?SubCategory
    {
        $identifier = $this->route('identifier');

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        return SubCategory::query()
            ->where('slug', $identifier)
            ->when(
                ctype_digit($identifier),
                fn (EloquentBuilder $query): EloquentBuilder => $query->orWhere(
                    (new SubCategory)->getQualifiedKeyName(),
                    (int) $identifier,
                ),
            )
            ->first();
    }
}
