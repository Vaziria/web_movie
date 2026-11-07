<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /** Throwaway public/ folder, so uploaded test thumbnails never land in the real public/uploads. */
    private ?string $testPublicPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Views must render without a built Vite manifest.
        $this->withoutVite();

        $this->testPublicPath = sys_get_temp_dir().'/moviemov-public-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->testPublicPath);
        $this->app->usePublicPath($this->testPublicPath);
    }

    protected function tearDown(): void
    {
        if ($this->testPublicPath) {
            File::deleteDirectory($this->testPublicPath);
        }

        parent::tearDown();
    }
}
