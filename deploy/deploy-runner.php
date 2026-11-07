<?php

use Illuminate\Contracts\Console\Kernel;

/*
 * One-time deploy runner for Hostinger shared hosting (no SSH).
 * Upload release.zip and this file (renamed with a random token) next to each other, request it once over
 * HTTPS, read the log, and it deletes itself. Keeps an existing .env and public/uploads/.
 */

@set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$root = __DIR__;
$zipPath = $root.'/release.zip';

// When dropped into public/ on a redeploy, the app root is one level up.
if (! is_file($zipPath) && is_file(dirname($root).'/release.zip')) {
    $root = dirname($root);
    $zipPath = $root.'/release.zip';
}

function step(string $msg): void
{
    echo '['.date('H:i:s')."] {$msg}\n";
    @ob_flush();
    @flush();
}

$zip = new ZipArchive;
if ($zip->open($zipPath) !== true) {
    step('ERROR: cannot open release.zip');
    @unlink(__FILE__);
    exit;
}

$keepEnv = is_file($root.'/.env');
$names = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($keepEnv && $name === '.env') {
        continue;
    }
    if (str_starts_with($name, 'public/uploads/')) {
        continue;
    }
    $names[] = $name;
}
step('extracting '.count($names).' files'.($keepEnv ? ' (keeping existing .env)' : ''));
if (! $zip->extractTo($root, $names)) {
    step('ERROR: extract failed');
    @unlink(__FILE__);
    exit;
}
$zip->close();
@unlink($zipPath);
@unlink($root.'/default.php');

foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'public/uploads/thumbnails'] as $dir) {
    if (! is_dir($root.'/'.$dir)) {
        @mkdir($root.'/'.$dir, 0755, true);
    }
}
// Stale cached config/routes from a previous release must not survive.
foreach (glob($root.'/bootstrap/cache/*.php') ?: [] as $f) {
    @unlink($f);
}

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

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

@unlink(__FILE__);
