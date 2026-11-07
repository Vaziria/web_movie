<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email? : Defaults to admin@<APP_URL host>} {--name=Admin} {--password= : Omit to be prompted}';

    protected $description = 'Create or promote an admin user who can log in to the upload API';

    public function handle(): int
    {
        $email = $this->argument('email') ?: 'admin@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $password = $this->option('password') ?: $this->secret('Password (min 8 chars)');

        $v = Validator::make(compact('email', 'password'), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $user->name ?: $this->option('name');
        $user->password = $password;
        $user->is_admin = true;
        $user->save();

        $this->info("Admin ready: {$user->email}");

        return self::SUCCESS;
    }
}
