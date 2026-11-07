<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        // Accept a pasted `<iframe src="...">` embed code as embed_url by pulling out its src.
        $embed = $this->input('embed_url');
        if (is_string($embed) && preg_match('~<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $embed, $m)) {
            $this->merge(['embed_url' => html_entity_decode(trim($m[1]))]);
        }

        // Multipart forms send categories as "Action, Drama"; JSON sends an array.
        $categories = $this->input('categories');
        if (is_string($categories)) {
            $this->merge(['categories' => array_values(array_filter(array_map('trim', explode(',', $categories))))]);
        }
    }

    public function rules(): array
    {
        $movie = $this->route('movie');
        $required = $movie ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'alpha_dash', 'max:255', Rule::unique('movies', 'slug')->ignore($movie?->id)],
            'embed_url' => [$required, 'string', 'max:2048', 'url:https'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https', 'prohibits:thumbnail'],
            'thumbnail' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'synopsis' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'release_year' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,1440'],
            'categories' => ['sometimes', 'array', 'max:10'],
            'categories.*' => ['string', 'max:100'],
        ];
    }
}
