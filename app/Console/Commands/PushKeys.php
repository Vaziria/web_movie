<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushKeys extends Command
{
    protected $signature = 'app:push-keys
        {--env-file= : .env file to write (default: the app .env)}
        {--force : Replace existing keys (every current subscriber is lost)}';

    protected $description = 'Create the VAPID key pair for push notifications and save it in .env (prints only the public key)';

    public function handle(): int
    {
        $file = $this->option('env-file') ?: base_path('.env');
        if (! is_file($file) || ! is_writable($file)) {
            $this->error("Cannot write {$file}");

            return self::FAILURE;
        }

        $env = (string) file_get_contents($file);
        $has = preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $env) && preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $env);
        if ($has && ! $this->option('force')) {
            preg_match('/^VAPID_PUBLIC_KEY=(.+)$/m', $env, $m);
            $this->info('Keys already set. Public key: '.trim($m[1]));

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.$key.'=.*$/m';
            $env = preg_match($pattern, $env)
                ? preg_replace_callback($pattern, fn () => $line, $env, 1)
                : rtrim($env, "\n")."\n".$line."\n";
        }
        file_put_contents($file, $env, LOCK_EX);

        $this->info('Keys saved to '.basename($file).'. Public key: '.$keys['publicKey']);
        $this->line('Run `php artisan config:cache` if the config is cached.');

        return self::SUCCESS;
    }
}
