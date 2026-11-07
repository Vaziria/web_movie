<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        // Derive the slug from the name when creating without an explicit slug.
        if (! $this->route('category') && blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $required = $category ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'slug' => [$required, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => 'The slug may only contain lowercase letters, numbers and single dashes.'];
    }
}
