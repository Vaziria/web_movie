<?php

namespace App\Http\Requests;

/** Updating a tag: all fields optional. Separate class so the generated API docs show that. */
class UpdateTagRequest extends TagRequest
{
    protected function creating(): bool
    {
        return false;
    }
}
