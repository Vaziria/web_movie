<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SetAdminEmail extends Command
{
    protected $signature = 'app:admin-email {current : The admin\'s current login email} {new : The new login email}';

    protected $description = 'Change an admin\'s login email (password reset links are sent there)';

    public function handle(): int
    {
        $current = (string) $this->argument('current');
        $new = (string) $this->argument('new');

        $v = Validator::make(['email' => $new], ['email' => ['required', 'email', 'max:255']]);
        if ($v->fails()) {
            $this->error($v->errors()->first('email'));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $current)->where('is_admin', true)->first();
        if (! $user) {
            $this->error("No admin with the email {$current}.");

            return self::FAILURE;
        }
        if (User::query()->where('email', $new)->whereKeyNot($user->id)->exists()) {
            $this->error("Another user already uses {$new}.");

            return self::FAILURE;
        }

        $user->forceFill(['email' => $new])->save();
        $this->info("Admin login email is now {$new}");

        return self::SUCCESS;
    }
}
