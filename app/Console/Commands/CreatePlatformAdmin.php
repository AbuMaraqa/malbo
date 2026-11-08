<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a platform admin, or changes the name/password of an existing one.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin
        {email : Sign-in email}
        {--name= : Display name (asked for a new admin when missing)}
        {--password= : Password (asked when missing)}';

    protected $description = 'Create or update a platform admin';

    public function handle(): int
    {
        // Login lowers its input and PostgreSQL compares exactly.
        $email = Str::lower((string) $this->argument('email'));
        $admin = PlatformAdmin::firstWhere('email', $email);

        $values = [
            'email' => $email,
            'name' => $this->option('name') ?: ($admin?->name ?? $this->ask('Name')),
            'password' => $this->option('password') ?: $this->secret('Password'),
        ];
        $values['password_confirmation'] = $this->option('password') ?: $this->secret('Confirm the password');

        $validator = Validator::make($values, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin ??= new PlatformAdmin(['email' => $email]);
        $admin->fill(['name' => $values['name'], 'password' => $values['password']]);
        $admin->setRememberToken(Str::random(60)); // a new password signs out remembered devices
        $admin->save();

        $this->components->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated')." platform admin [{$email}].");

        return self::SUCCESS;
    }
}
