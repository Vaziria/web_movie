<?php

namespace App\Http\Requests;

/**
 * Updating a movie (API): same fields as creating, all optional. Only the fields you send change.
 * A separate class so the generated API docs show the update fields as optional.
 */
class UpdateMovieRequest extends MovieRequest
{
    protected function creating(): bool
    {
        return false;
    }
}
