<?php

use Illuminate\Contracts\Console\Kernel;

/*
 * One-time deploy runner for Hostinger shared hosting (no SSH). See docs/DEPLOY.md.
 *
 * Put release.zip in the app root (public_html/, not web-accessible) and this file, renamed with a random token,
 * anywhere under public_html/public/. Request it once over HTTPS, read the log; it deletes itself.
 * Keeps the existing .env and public/uploads/. Removes anything in public/ that is not part of the release.
 */

@set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

function step(string $msg): void
{
    echo '['.date('H:i:s')."] {$msg}\n";
    @ob_flush();
    @flush();
}

function finish(): never
{
    @unlink(__FILE__);
    exit;
}

function rrmdir(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            rrmdir($path.'/'.$entry);
        }
    }
    @rmdir($path);
}

// App root = the top-most of this folder and up to 3 parents that contains `artisan`.
$root = null;
$dir = __DIR__;
for ($i = 0; $i <= 3; $i++) {
    if (is_file($dir.'/artisan')) {
        $root = $dir;
    }
    $dir = dirname($dir);
}
if ($root === null) {
    step('ERROR: app root (folder with artisan) not found');
    finish();
}

$zipPath = null;
foreach ([$root, __DIR__, $root.'/public'] as $candidate) {
    if (is_file($candidate.'/release.zip')) {
        $zipPath = $candidate.'/release.zip';
        break;
    }
}
if ($zipPath === null) {
    step('ERROR: release.zip not found in '.$root);
    finish();
}
step("app root: {$root}");

$zip = new ZipArchive;
if ($zip->open($zipPath) !== true) {
    step('ERROR: cannot open release.zip');
    finish();
}

$keepEnv = is_file($root.'/.env');
$names = [];
$publicTop = ['uploads' => true];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($keepEnv && $name === '.env') {
        continue;
    }
    if (str_starts_with($name, 'public/uploads/')) {
        continue;
    }
    if (str_starts_with($name, 'public/')) {
        $publicTop[explode('/', substr($name, 7))[0]] = true;
    }
    $names[] = $name;
}

// Remove stray entries in public/ (old builds, leftovers) before extracting.
foreach (scandir($root.'/public') ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || isset($publicTop[$entry])) {
        continue;
    }
    rrmdir($root.'/public/'.$entry);
    step("removed public/{$entry}");
}

step('extracting '.count($names).' files'.($keepEnv ? ' (keeping existing .env)' : ''));
if (! $zip->extractTo($root, $names)) {
    step('ERROR: extract failed');
    finish();
}
$zip->close();
@unlink($zipPath);
@unlink($root.'/default.php');

foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'public/uploads/thumbnails'] as $d) {
    if (! is_dir($root.'/'.$d)) {
        @mkdir($root.'/'.$d, 0755, true);
    }
}
// Stale cached config/routes from a previous release must not survive.
foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $f) {
    @unlink($f);
}

if (! is_file($root.'/.env')) {
    step('ERROR: no .env in app root; refusing to run artisan');
    finish();
}

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    step('database: '.config('database.default').' / '.config('database.connections.'.config('database.default').'.database'));

    $run = function (string $cmd, array $args = []) use ($kernel) {
        $code = $kernel->call($cmd, $args);
        step("artisan {$cmd} => exit {$code}");
        $out = trim($kernel->output());
        if ($out !== '') {
            echo preg_replace('/^/m', '    ', $out)."\n";
        }

        return $code;
    };

    $run('migrate', ['--force' => true]);

    $initFile = $root.'/storage/app/private/admin-init.txt';
    if (is_file($initFile)) {
        $run('app:create-admin', ['--password' => trim((string) file_get_contents($initFile))]);
        @unlink($initFile);
    }

    $run('config:cache');
    $run('route:cache');
    $run('view:cache');
    step('DEPLOY_OK');
} catch (Throwable $e) {
    step('ERROR: '.get_class($e).': '.$e->getMessage());
}

finish();
