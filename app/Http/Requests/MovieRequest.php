<?php

namespace App\Http\Requests;

use App\Models\Movie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

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
        if (is_string($embed)) {
            $this->merge(['embed_url' => Movie::extractEmbedSrc($embed)]);
        }

        // Backup players: JSON array, or one per line (admin textarea / multipart). Each may be iframe code.
        $backups = $this->input('backup_embed_urls');
        if (is_string($backups)) {
            $backups = preg_split('/\R/', $backups);
        }
        if (is_array($backups)) {
            $this->merge(['backup_embed_urls' => array_values(array_filter(
                array_map(fn ($b) => is_string($b) ? Movie::extractEmbedSrc($b) : $b, $backups),
                fn ($b) => $b !== '' && $b !== null,
            ))]);
        }

        // Multipart forms send categories as "Action, Drama"; JSON sends an array.
        $categories = $this->input('categories');
        if (is_string($categories)) {
            $this->merge(['categories' => array_values(array_filter(array_map('trim', explode(',', $categories))))]);
        }

        // A thumbnail file wins over a URL. In the admin form an empty URL box means "keep the current thumbnail"
        // (removal is an explicit checkbox), so drop it instead of letting it clear the field.
        if ($this->hasFile('thumbnail') || ($this->boolean('_admin_form') && blank($this->input('thumbnail_url')))) {
            $this->request->remove('thumbnail_url');
        }

        // Admin form: the date box only counts when "Schedule" is picked.
        if ($this->boolean('_admin_form') && $this->input('status') !== 'scheduled') {
            $this->request->remove('published_at');
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
            'backup_embed_urls' => ['sometimes', 'nullable', 'array', 'max:'.Movie::MAX_BACKUPS],
            'backup_embed_urls.*' => ['string', 'max:2048', 'url:https'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'thumbnail' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'synopsis' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'release_year' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'between:1,1440'],
            'categories' => ['sometimes', 'nullable', 'array', 'max:10'],
            'categories.*' => ['string', 'max:100'],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,published,scheduled'],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
        ];
    }

    /** A scheduled time must be in the future; "published" cannot be in the future. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('published_at') || blank($this->input('published_at'))) {
                return;
            }
            try {
                $at = Movie::parsePublishedAt($this->input('published_at'));
            } catch (Throwable) {
                $validator->errors()->add('published_at', 'The published at field must be a valid date.');

                return;
            }
            $status = $this->input('status');
            if ($status === 'scheduled' && ! $at->isFuture()) {
                $validator->errors()->add('published_at', 'The scheduled time must be in the future.');
            } elseif ($status === 'published' && $at->isFuture()) {
                $validator->errors()->add('published_at', 'A future time needs status "scheduled".');
            }
        }];
    }
}
