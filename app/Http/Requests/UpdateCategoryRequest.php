<?php

namespace App\Http\Requests;

/** Updating a category: all fields optional. Separate class so the generated API docs show that. */
class UpdateCategoryRequest extends CategoryRequest
{
    protected function creating(): bool
    {
        return false;
    }
}
