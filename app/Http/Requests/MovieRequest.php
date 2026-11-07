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

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $required = $creating ? 'required' : 'sometimes';
        $movie = $this->route('movie');

        return [
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'alpha_dash', 'max:255', Rule::unique('movies', 'slug')->ignore($movie?->id)],
            'embed_url' => [$required, 'string', 'max:2048', 'url:https'],
            'poster_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'synopsis' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'release_year' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,1440'],
        ];
    }
}
