<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestIsolationTest extends TestCase
{
    public function test_tests_use_a_throwaway_public_folder(): void
    {
        // Uploaded test thumbnails must never land in (or be cleaned out of) the real public/uploads.
        $this->assertNotSame(realpath(base_path('public')), realpath(public_path()));
        $this->assertStringContainsString('moviemov-public-', public_path());
        $this->assertDirectoryExists(public_path());
    }
}
